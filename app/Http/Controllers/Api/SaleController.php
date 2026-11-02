<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ManagesStock;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaleRequest;
use App\Models\Customer;
use App\Models\PaymentAllocation;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\PartyBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    use ManagesStock;

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 10), 100);

        $sales = Sale::query()
            ->with('customer:id,name')
            ->withCount('items')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('reference_no', 'like', "%{$search}%")
                      ->orWhere('customer_name', 'like', "%{$search}%")
                      ->orWhere('customer_phone', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->input('payment_status')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('sale_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('sale_date', '<=', $request->input('date_to')))
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Sales fetched successfully.',
            'data' => $sales,
        ]);
    }

    public function store(SaleRequest $request): JsonResponse
    {
        $data = $request->validated();

        $sale = DB::transaction(function () use ($data) {
            // Saved customer, a walk-in saved with this sale, or a plain walk-in
            $customer = null;
            if (! empty($data['customer_id'])) {
                $customer = Customer::lockForUpdate()->find($data['customer_id']);
            } elseif (! empty($data['save_customer'])) {
                $customer = Customer::create([
                    'name' => $data['customer_name'],
                    'phone' => $data['customer_phone'] ?? null,
                    'opening_balance' => 0,
                    'credit_limit' => 0,
                    'is_active' => true,
                ]);
            }

            $products = $this->lockProducts(collect($data['items'])->pluck('product_id'));
            $figures = $this->calculate($data, $products, $customer);
            [$customerName, $customerPhone] = $this->customerSnapshot($data, $customer);

            $sale = Sale::create([
                'customer_id' => $customer?->id,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'sale_date' => $data['sale_date'],
                'subtotal' => $figures['subtotal'],
                'discount' => $figures['discount'],
                'total' => $figures['total'],
                'paid_amount' => $figures['paid'],
                'payment_status' => $figures['status'],
                'notes' => $data['notes'] ?? null,
            ]);

            $sale->update(['reference_no' => 'INV-' . str_pad((string) $sale->id, 5, '0', STR_PAD_LEFT)]);

            $changes = [];
            foreach ($figures['lines'] as $line) {
                $sale->items()->create($line);
                $changes[$line['product_id']] = ($changes[$line['product_id']] ?? 0) - $line['quantity'];
            }
            $this->applyStock($products, $changes, 'sale', $sale);

            return $sale;
        });

        return response()->json([
            'success' => true,
            'message' => 'Sale created successfully.',
            'data' => $sale->load(['customer:id,name', 'items.product:id,name,sku']),
        ], 201);
    }

    public function show(Sale $sale): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Sale fetched successfully.',
            'data' => $sale->load([
                'customer:id,name,phone',
                'items.product:id,name,sku,unit_id',
                'items.product.unit:id,short_name',
                'returns' => fn ($q) => $q->where('status', 'completed')
                    ->select('id', 'sale_id', 'reference_no', 'return_date', 'total'),
                'paymentAllocations' => fn ($q) => $q->whereHas('payment', fn ($p) => $p->where('status', 'completed'))
                    ->select('id', 'sale_id', 'payment_id', 'amount'),
            ]),
        ]);
    }

    public function update(SaleRequest $request, Sale $sale): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $sale) {
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);
            abort_if($sale->status === 'cancelled', 409, 'A cancelled sale cannot be edited.');
            abort_if(
                SaleReturn::where('sale_id', $sale->id)->where('status', 'completed')->exists(),
                409,
                'This sale has returns, so it cannot be edited. Cancel the returns first.'
            );
            abort_if(
                PaymentAllocation::where('sale_id', $sale->id)
                    ->whereHas('payment', fn ($q) => $q->where('status', 'completed'))
                    ->exists(),
                409,
                'This sale has payments recorded against it, so it cannot be edited. Cancel the payments first.'
            );

            $oldItems = $sale->items()->get();

            // What this sale already took from stock, and the cost recorded at the time of sale
            $oldQty = [];
            $oldCost = [];
            foreach ($oldItems as $old) {
                $oldQty[$old->product_id] = ($oldQty[$old->product_id] ?? 0) + (float) $old->quantity;
                $oldCost[$old->product_id] = $old->unit_cost;
            }

            // On edit a customer can be selected or left as walk-in. New customers are not created here.
            $customer = ! empty($data['customer_id'])
                ? Customer::lockForUpdate()->find($data['customer_id'])
                : null;

            $productIds = $oldItems->pluck('product_id')->merge(collect($data['items'])->pluck('product_id'));
            $products = $this->lockProducts($productIds);

            $figures = $this->calculate($data, $products, $customer, $oldQty, $oldCost, $sale->id);
            [$customerName, $customerPhone] = $this->customerSnapshot($data, $customer);

            // Net stock change: give back the old quantity, take the new quantity
            $changes = [];
            foreach ($oldQty as $productId => $qty) {
                $changes[$productId] = ($changes[$productId] ?? 0) + $qty;
            }
            foreach ($figures['lines'] as $line) {
                $changes[$line['product_id']] = ($changes[$line['product_id']] ?? 0) - $line['quantity'];
            }
            $this->applyStock($products, $changes, 'sale_edit', $sale);

            $sale->items()->delete();
            foreach ($figures['lines'] as $line) {
                $sale->items()->create($line);
            }

            $sale->update([
                'customer_id' => $customer?->id,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'sale_date' => $data['sale_date'],
                'subtotal' => $figures['subtotal'],
                'discount' => $figures['discount'],
                'total' => $figures['total'],
                'paid_amount' => $figures['paid'],
                'payment_status' => $figures['status'],
                'notes' => $data['notes'] ?? null,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Sale updated successfully.',
            'data' => $sale->refresh()->load(['customer:id,name', 'items.product:id,name,sku']),
        ]);
    }

    public function cancel(Request $request, Sale $sale): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        DB::transaction(function () use ($sale, $validated) {
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);
            abort_if($sale->status === 'cancelled', 409, 'This sale is already cancelled.');
            abort_if(
                SaleReturn::where('sale_id', $sale->id)->where('status', 'completed')->exists(),
                409,
                'This sale has returns, so it cannot be cancelled. Cancel the returns first.'
            );
            abort_if(
                PaymentAllocation::where('sale_id', $sale->id)
                    ->whereHas('payment', fn ($q) => $q->where('status', 'completed'))
                    ->exists(),
                409,
                'This sale has payments recorded against it, so it cannot be cancelled. Cancel the payments first.'
            );

            $items = $sale->items()->get();
            $products = $this->lockProducts($items->pluck('product_id'));

            // Cancelling a sale puts the sold stock back
            $changes = [];
            foreach ($items as $item) {
                $changes[$item->product_id] = ($changes[$item->product_id] ?? 0) + (float) $item->quantity;
            }
            $this->applyStock($products, $changes, 'sale_cancel', $sale);

            $sale->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $validated['reason'],
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Sale cancelled successfully.',
            'data' => $sale->refresh(),
        ]);
    }

    /**
     * Totals, stock check, walk-in rule and credit limit. Never trusted from the browser.
     *
     * $oldQty / $oldCost: quantities and cost snapshots from the sale being edited (empty for a new sale).
     */
    private function calculate(
        array $data,
        Collection $products,
        ?Customer $customer,
        array $oldQty = [],
        array $oldCost = [],
        ?int $ignoreSaleId = null,
    ): array {
        $subtotal = 0;
        $lines = [];

        foreach ($data['items'] as $index => $item) {
            $product = $products[(int) $item['product_id']];
            $quantity = round((float) $item['quantity'], 2);
            $unitPrice = round((float) $item['unit_price'], 2);
            $lineTotal = round($quantity * $unitPrice, 2);
            $subtotal += $lineTotal;

            // When editing, the quantity this sale already holds is available again
            $available = round((float) $product->stock_quantity + ($oldQty[$product->id] ?? 0), 2);
            if ($quantity > $available) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => ["Only {$available} in stock for {$product->name}."],
                ]);
            }

            $lines[] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                // Keep the original cost snapshot on edit, so past profit does not change
                'unit_cost' => $oldCost[$product->id] ?? $product->cost_price,
                'line_total' => $lineTotal,
            ];
        }

        $subtotal = round($subtotal, 2);
        $discount = round((float) ($data['discount'] ?? 0), 2);
        $paid = round((float) ($data['paid_amount'] ?? 0), 2);

        if ($discount > $subtotal) {
            throw ValidationException::withMessages(['discount' => ['Discount cannot be greater than the subtotal.']]);
        }

        $total = round($subtotal - $discount, 2);

        if ($paid > $total) {
            throw ValidationException::withMessages(['paid_amount' => ['Paid amount cannot be greater than the total.']]);
        }

        $due = round($total - $paid, 2);

        if ($due > 0) {
            // Credit is only allowed for saved customers
            if (! $customer) {
                throw ValidationException::withMessages([
                    'paid_amount' => ['A walk-in customer must pay in full. Select or save a customer to sell on credit.'],
                ]);
            }

            // Credit limit check (0 means no limit). Cancelled sales and this sale itself are not counted.
            if ((float) $customer->credit_limit > 0) {
                $previousDue = (float) Sale::where('customer_id', $customer->id)
                    ->where('status', 'completed')
                    ->when($ignoreSaleId, fn ($q) => $q->where('id', '!=', $ignoreSaleId))
                    ->selectRaw('COALESCE(SUM(total - paid_amount), 0) as due')
                    ->value('due');
                $returnAdjustments = (float) SaleReturn::where('customer_id', $customer->id)
                    ->where('status', 'completed')
                    ->sum('due_adjustment');
                $outstanding = PartyBalance::customerOpeningOutstanding($customer) + $previousDue - $returnAdjustments + $due;

                if ($outstanding > (float) $customer->credit_limit) {
                    throw ValidationException::withMessages([
                        'paid_amount' => [
                            'This sale exceeds the credit limit of ' . number_format((float) $customer->credit_limit, 2)
                            . '. Outstanding balance would be ' . number_format($outstanding, 2) . '.',
                        ],
                    ]);
                }
            }
        }

        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
            'paid' => $paid,
            'status' => $paid <= 0 ? 'unpaid' : ($paid >= $total ? 'paid' : 'partial'),
        ];
    }

    /**
     * Customer name and phone are copied onto the sale, so old invoices never change.
     */
    private function customerSnapshot(array $data, ?Customer $customer): array
    {
        $name = $data['customer_name'] ?? null;
        $phone = $data['customer_phone'] ?? null;

        if ($customer) {
            $name = $customer->name;
            $phone = $phone ?: $customer->phone;
        }

        return [$name ?: 'Walk-in Customer', $phone];
    }
}