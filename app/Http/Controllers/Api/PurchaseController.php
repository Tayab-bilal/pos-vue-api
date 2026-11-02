<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ManagesStock;
use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseRequest;
use App\Models\PaymentAllocation;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    use ManagesStock;

    private const SHORTAGE = 'Not enough stock of :product. Part of the purchased quantity has already been sold.';

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 10), 100);

        $purchases = Purchase::query()
            ->with('supplier:id,name')
            ->withCount('items')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('supplier_name', 'like', "%{$search}%")
                        ->orWhere('supplier_phone', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->input('payment_status')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('purchase_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('purchase_date', '<=', $request->input('date_to')))
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Purchases fetched successfully.',
            'data' => $purchases,
        ]);
    }

    public function store(PurchaseRequest $request): JsonResponse
    {
        $data = $request->validated();

        $purchase = DB::transaction(function () use ($data) {
            $figures = $this->calculate($data);
            $products = $this->lockProducts(collect($figures['lines'])->pluck('product_id'));
            [$supplierName, $supplierPhone] = $this->supplierSnapshot($data);

            $purchase = Purchase::create([
                'supplier_id' => $data['supplier_id'] ?? null,
                'supplier_name' => $supplierName,
                'supplier_phone' => $supplierPhone,
                'purchase_date' => $data['purchase_date'],
                'subtotal' => $figures['subtotal'],
                'discount' => $figures['discount'],
                'total' => $figures['total'],
                'paid_amount' => $figures['paid'],
                'payment_status' => $figures['status'],
                'notes' => $data['notes'] ?? null,
            ]);

            $purchase->update(['reference_no' => 'PUR-'.str_pad((string) $purchase->id, 5, '0', STR_PAD_LEFT)]);

            $changes = [];
            foreach ($figures['lines'] as $line) {
                $purchase->items()->create($line);
                $changes[$line['product_id']] = ($changes[$line['product_id']] ?? 0) + $line['quantity'];
            }
            $this->applyStock($products, $changes, 'purchase', $purchase);

            return $purchase;
        });

        return response()->json([
            'success' => true,
            'message' => 'Purchase created successfully.',
            'data' => $purchase->load(['supplier:id,name', 'items.product:id,name,sku']),
        ], 201);
    }

    public function show(Purchase $purchase): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Purchase fetched successfully.',
            'data' => $purchase->load([
                'supplier:id,name,phone',
                'items.product:id,name,sku,unit_id',
                'items.product.unit:id,short_name',
                'returns' => fn ($q) => $q->where('status', 'completed')
                    ->select('id', 'purchase_id', 'reference_no', 'return_date', 'total'),
                'paymentAllocations' => fn ($q) => $q->whereHas('payment', fn ($p) => $p->where('status', 'completed'))
                    ->select('id', 'purchase_id', 'payment_id', 'amount'),
            ]),
        ]);
    }

    public function update(PurchaseRequest $request, Purchase $purchase): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $purchase) {
            $purchase = Purchase::lockForUpdate()->findOrFail($purchase->id);
            abort_if($purchase->status === 'cancelled', 409, 'A cancelled purchase cannot be edited.');
            abort_if(
                PurchaseReturn::where('purchase_id', $purchase->id)->where('status', 'completed')->exists(),
                409,
                'This purchase has returns, so it cannot be edited. Cancel the returns first.'
            );
            abort_if(
                PaymentAllocation::where('purchase_id', $purchase->id)
                    ->whereHas('payment', fn ($q) => $q->where('status', 'completed'))
                    ->exists(),
                409,
                'This purchase has payments recorded against it, so it cannot be edited. Cancel the payments first.'
            );

            $oldItems = $purchase->items()->get();
            $figures = $this->calculate($data);

            $productIds = $oldItems->pluck('product_id')->merge(collect($figures['lines'])->pluck('product_id'));
            $products = $this->lockProducts($productIds);

            // Net stock change per product: new quantity minus old quantity
            $changes = [];
            foreach ($oldItems as $old) {
                $changes[$old->product_id] = ($changes[$old->product_id] ?? 0) - (float) $old->quantity;
            }
            foreach ($figures['lines'] as $line) {
                $changes[$line['product_id']] = ($changes[$line['product_id']] ?? 0) + $line['quantity'];
            }
            $this->applyStock($products, $changes, 'purchase_edit', $purchase, shortage: self::SHORTAGE);

            $purchase->items()->delete();
            foreach ($figures['lines'] as $line) {
                $purchase->items()->create($line);
            }

            [$supplierName, $supplierPhone] = $this->supplierSnapshot($data);

            $purchase->update([
                'supplier_id' => $data['supplier_id'] ?? null,
                'supplier_name' => $supplierName,
                'supplier_phone' => $supplierPhone,
                'purchase_date' => $data['purchase_date'],
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
            'message' => 'Purchase updated successfully.',
            'data' => $purchase->refresh()->load(['supplier:id,name', 'items.product:id,name,sku']),
        ]);
    }

    public function cancel(Request $request, Purchase $purchase): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        DB::transaction(function () use ($purchase, $validated) {
            $purchase = Purchase::lockForUpdate()->findOrFail($purchase->id);
            abort_if($purchase->status === 'cancelled', 409, 'This purchase is already cancelled.');
            abort_if(
                PurchaseReturn::where('purchase_id', $purchase->id)->where('status', 'completed')->exists(),
                409,
                'This purchase has returns, so it cannot be cancelled. Cancel the returns first.'
            );
            abort_if(
                PaymentAllocation::where('purchase_id', $purchase->id)
                    ->whereHas('payment', fn ($q) => $q->where('status', 'completed'))
                    ->exists(),
                409,
                'This purchase has payments recorded against it, so it cannot be cancelled. Cancel the payments first.'
            );

            $items = $purchase->items()->get();
            $products = $this->lockProducts($items->pluck('product_id'));

            // Cancelling a purchase removes the stock it added
            $changes = [];
            foreach ($items as $item) {
                $changes[$item->product_id] = ($changes[$item->product_id] ?? 0) - (float) $item->quantity;
            }
            $this->applyStock($products, $changes, 'purchase_cancel', $purchase, shortage: self::SHORTAGE);

            $purchase->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $validated['reason'],
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Purchase cancelled successfully.',
            'data' => $purchase->refresh(),
        ]);
    }

    /**
     * Totals are always calculated here, never trusted from the browser.
     */
    private function calculate(array $data): array
    {
        $subtotal = 0;
        $lines = [];

        foreach ($data['items'] as $item) {
            $quantity = round((float) $item['quantity'], 2);
            $unitCost = round((float) $item['unit_cost'], 2);
            $lineTotal = round($quantity * $unitCost, 2);
            $subtotal += $lineTotal;

            $lines[] = [
                'product_id' => (int) $item['product_id'],
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
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

        // Credit is only allowed for saved suppliers
        if (empty($data['supplier_id']) && $paid < $total) {
            throw ValidationException::withMessages(['paid_amount' => ['A one-time supplier must be paid in full. Select a saved supplier to buy on credit.']]);
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
     * Supplier name and phone are copied onto the purchase, so old purchases never change.
     */
    private function supplierSnapshot(array $data): array
    {
        $name = $data['supplier_name'] ?? null;
        $phone = $data['supplier_phone'] ?? null;

        if (! empty($data['supplier_id'])) {
            $supplier = Supplier::withTrashed()->find($data['supplier_id']);
            $name = $supplier->name;
            $phone = $phone ?: $supplier->phone;
        }

        return [$name, $phone];
    }
}
