<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ManagesStock;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaleReturnRequest;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleReturnController extends Controller
{
    use ManagesStock;

    private const SHORTAGE = 'Not enough stock of :product. Part of the returned quantity has already been sold again.';

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 10), 100);

        $returns = SaleReturn::query()
            ->with('sale:id,reference_no')
            ->withCount('items')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('reference_no', 'like', "%{$search}%")
                      ->orWhere('customer_name', 'like', "%{$search}%")
                      ->orWhereHas('sale', fn ($s) => $s->where('reference_no', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('return_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('return_date', '<=', $request->input('date_to')))
            ->orderByDesc('return_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Sales returns fetched successfully.',
            'data' => $returns,
        ]);
    }

    /**
     * What can still be returned from a sale. The return form uses this.
     */
    public function returnable(Sale $sale): JsonResponse
    {
        $sale->load(['items.product:id,name,sku,unit_id', 'items.product.unit:id,short_name']);

        $returned = $this->returnedQuantities($sale->id);

        $items = $sale->items->map(function ($item) use ($returned) {
            $sold = (float) $item->quantity;
            $already = (float) ($returned[$item->product_id] ?? 0);

            return [
                'product_id' => $item->product_id,
                'name' => $item->product?->name,
                'sku' => $item->product?->sku,
                'unit' => $item->product?->unit?->short_name,
                'sold' => $sold,
                'returned' => $already,
                'remaining' => round($sold - $already, 2),
                'unit_price' => (float) $item->unit_price,
            ];
        })->values();

        [$maxDue, $maxCash] = $this->limits($sale);
        $subtotal = (float) $sale->subtotal;

        return response()->json([
            'success' => true,
            'message' => 'Returnable items fetched successfully.',
            'data' => [
                'sale' => [
                    'id' => $sale->id,
                    'reference_no' => $sale->reference_no,
                    'sale_date' => $sale->sale_date->format('Y-m-d'),
                    'status' => $sale->status,
                    'customer_id' => $sale->customer_id,
                    'customer_name' => $sale->customer_name,
                    'customer_phone' => $sale->customer_phone,
                    'subtotal' => (float) $sale->subtotal,
                    'discount' => (float) $sale->discount,
                    'total' => (float) $sale->total,
                    'paid_amount' => (float) $sale->paid_amount,
                ],
                'items' => $items,
                'discount_ratio' => $subtotal > 0 ? round((float) $sale->total / $subtotal, 6) : 1,
                'max_due_adjustment' => $maxDue,
                'max_cash_refund' => $maxCash,
                'can_return' => $sale->status === 'completed' && $items->contains(fn ($i) => $i['remaining'] > 0),
            ],
        ]);
    }

    public function store(SaleReturnRequest $request): JsonResponse
    {
        $data = $request->validated();

        $saleReturn = DB::transaction(function () use ($data) {
            // Lock the sale so two returns cannot take the same quantity at once
            $sale = Sale::lockForUpdate()->findOrFail($data['sale_id']);
            abort_if($sale->status === 'cancelled', 409, 'A cancelled sale cannot have returns.');

            if ($data['return_date'] < $sale->sale_date->format('Y-m-d')) {
                throw ValidationException::withMessages(['return_date' => ['The return date cannot be before the sale date.']]);
            }

            $soldItems = $sale->items()->get()->keyBy('product_id');
            $returned = $this->returnedQuantities($sale->id);

            $subtotal = $total = 0;
            $lines = [];
            $subtotalOfSale = (float) $sale->subtotal;
            $ratio = $subtotalOfSale > 0 ? (float) $sale->total / $subtotalOfSale : 1;

            foreach ($data['items'] as $index => $item) {
                $soldItem = $soldItems->get((int) $item['product_id']);

                if (! $soldItem) {
                    throw ValidationException::withMessages(["items.{$index}.product_id" => ['This product is not on the original sale.']]);
                }

                $quantity = round((float) $item['quantity'], 2);
                $remaining = round((float) $soldItem->quantity - (float) ($returned[$soldItem->product_id] ?? 0), 2);

                if ($quantity > $remaining) {
                    throw ValidationException::withMessages(["items.{$index}.quantity" => ["Only {$remaining} can still be returned."]]);
                }

                $gross = round($quantity * (float) $soldItem->unit_price, 2);
                $net = round($gross * $ratio, 2); // the invoice discount is shared across returned items

                $subtotal += $gross;
                $total += $net;

                $lines[] = [
                    'product_id' => $soldItem->product_id,
                    'quantity' => $quantity,
                    'unit_price' => $soldItem->unit_price,
                    'unit_cost' => $soldItem->unit_cost,
                    'line_total' => $net,
                    'restock' => (bool) ($item['restock'] ?? true),
                ];
            }

            $subtotal = round($subtotal, 2);
            $total = round($total, 2);
            $discount = round($subtotal - $total, 2);

            // Work out how the customer is paid back
            [$maxDue, $maxCash] = $this->limits($sale);

            if ($data['refund_method'] === 'adjust_due' && ! $sale->customer_id) {
                throw ValidationException::withMessages(['refund_method' => ['A walk-in sale can only be refunded in cash.']]);
            }

            $dueAdjustment = $data['refund_method'] === 'adjust_due' ? min($total, $maxDue) : 0;
            $cashRefund = round($total - $dueAdjustment, 2);

            if ($cashRefund > $maxCash + 0.004) {
                throw ValidationException::withMessages([
                    'refund_method' => [
                        'The cash refund (' . number_format($cashRefund, 2) . ') is more than the customer has paid ('
                        . number_format($maxCash, 2) . '). Choose "Adjust against due" instead.',
                    ],
                ]);
            }

            $saleReturn = SaleReturn::create([
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'customer_name' => $sale->customer_name,
                'customer_phone' => $sale->customer_phone,
                'return_date' => $data['return_date'],
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'due_adjustment' => $dueAdjustment,
                'cash_refund' => $cashRefund,
                'refund_method' => $data['refund_method'],
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $saleReturn->update(['reference_no' => 'SRN-' . str_pad((string) $saleReturn->id, 5, '0', STR_PAD_LEFT)]);

            // Only items that are not damaged go back into stock
            $changes = [];
            foreach ($lines as $line) {
                $saleReturn->items()->create($line);
                if ($line['restock']) {
                    $changes[$line['product_id']] = ($changes[$line['product_id']] ?? 0) + $line['quantity'];
                }
            }
            $this->applyStock(
                $this->lockProducts(collect(array_keys($changes))),
                $changes,
                'sale_return',
                $saleReturn,
                shortage: self::SHORTAGE,
            );

            return $saleReturn;
        });

        return response()->json([
            'success' => true,
            'message' => 'Sales return created successfully.',
            'data' => $saleReturn->load(['sale:id,reference_no', 'items.product:id,name,sku']),
        ], 201);
    }

    public function show(SaleReturn $saleReturn): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Sales return fetched successfully.',
            'data' => $saleReturn->load([
                'sale:id,reference_no,sale_date',
                'items.product:id,name,sku,unit_id',
                'items.product.unit:id,short_name',
            ]),
        ]);
    }

    public function cancel(Request $request, SaleReturn $saleReturn): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        DB::transaction(function () use ($saleReturn, $validated) {
            $saleReturn = SaleReturn::lockForUpdate()->findOrFail($saleReturn->id);
            abort_if($saleReturn->status === 'cancelled', 409, 'This return is already cancelled.');

            // Cancelling takes back the stock that this return added
            $changes = [];
            foreach ($saleReturn->items()->get() as $item) {
                if ($item->restock) {
                    $changes[$item->product_id] = ($changes[$item->product_id] ?? 0) - (float) $item->quantity;
                }
            }
            $this->applyStock(
                $this->lockProducts(collect(array_keys($changes))),
                $changes,
                'sale_return_cancel',
                $saleReturn,
                shortage: self::SHORTAGE,
            );

            $saleReturn->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $validated['reason'],
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Sales return cancelled successfully.',
            'data' => $saleReturn->refresh(),
        ]);
    }

    /**
     * Quantity already returned per product (cancelled returns are not counted).
     */
    private function returnedQuantities(int $saleId): Collection
    {
        return SaleReturnItem::query()
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->where('sale_returns.sale_id', $saleId)
            ->where('sale_returns.status', 'completed')
            ->whereNull('sale_returns.deleted_at')
            ->groupBy('sale_return_items.product_id')
            ->selectRaw('sale_return_items.product_id as product_id, SUM(sale_return_items.quantity) as qty')
            ->pluck('qty', 'product_id');
    }

    /**
     * Returns [max amount that can still reduce the sale's due, max cash that can still be refunded].
     */
    private function limits(Sale $sale): array
    {
        $totals = SaleReturn::where('sale_id', $sale->id)
            ->where('status', 'completed')
            ->selectRaw('COALESCE(SUM(due_adjustment), 0) as due_adjusted, COALESCE(SUM(cash_refund), 0) as cash_refunded')
            ->first();

        $maxDue = $sale->customer_id
            ? max(round((float) $sale->total - (float) $sale->paid_amount - (float) $totals->due_adjusted, 2), 0)
            : 0;
        $maxCash = max(round((float) $sale->paid_amount - (float) $totals->cash_refunded, 2), 0);

        return [$maxDue, $maxCash];
    }
}