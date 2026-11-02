<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ManagesStock;
use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseReturnRequest;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReturnController extends Controller
{
    use ManagesStock;

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 10), 100);

        $returns = PurchaseReturn::query()
            ->with('purchase:id,reference_no')
            ->withCount('items')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('reference_no', 'like', "%{$search}%")
                      ->orWhere('supplier_name', 'like', "%{$search}%")
                      ->orWhereHas('purchase', fn ($p) => $p->where('reference_no', 'like', "%{$search}%"));
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
            'message' => 'Purchase returns fetched successfully.',
            'data' => $returns,
        ]);
    }

    /**
     * What can still be returned from a purchase. The return form uses this.
     */
    public function returnable(Purchase $purchase): JsonResponse
    {
        $purchase->load([
            'items.product:id,name,sku,unit_id,stock_quantity',
            'items.product.unit:id,short_name',
        ]);

        $returned = $this->returnedQuantities($purchase->id);

        $items = $purchase->items->map(function ($item) use ($returned) {
            $bought = (float) $item->quantity;
            $already = (float) ($returned[$item->product_id] ?? 0);

            return [
                'product_id' => $item->product_id,
                'name' => $item->product?->name,
                'sku' => $item->product?->sku,
                'unit' => $item->product?->unit?->short_name,
                'purchased' => $bought,
                'returned' => $already,
                'remaining' => round($bought - $already, 2),
                'in_stock' => (float) ($item->product?->stock_quantity ?? 0),
                'unit_cost' => (float) $item->unit_cost,
            ];
        })->values();

        [$maxDue, $maxCash] = $this->limits($purchase);
        $subtotal = (float) $purchase->subtotal;

        return response()->json([
            'success' => true,
            'message' => 'Returnable items fetched successfully.',
            'data' => [
                'purchase' => [
                    'id' => $purchase->id,
                    'reference_no' => $purchase->reference_no,
                    'purchase_date' => $purchase->purchase_date->format('Y-m-d'),
                    'status' => $purchase->status,
                    'supplier_id' => $purchase->supplier_id,
                    'supplier_name' => $purchase->supplier_name,
                    'supplier_phone' => $purchase->supplier_phone,
                    'subtotal' => (float) $purchase->subtotal,
                    'discount' => (float) $purchase->discount,
                    'total' => (float) $purchase->total,
                    'paid_amount' => (float) $purchase->paid_amount,
                ],
                'items' => $items,
                'discount_ratio' => $subtotal > 0 ? round((float) $purchase->total / $subtotal, 6) : 1,
                'max_due_adjustment' => $maxDue,
                'max_cash_received' => $maxCash,
                'can_return' => $purchase->status === 'completed' && $items->contains(fn ($i) => $i['remaining'] > 0),
            ],
        ]);
    }

    public function store(PurchaseReturnRequest $request): JsonResponse
    {
        $data = $request->validated();

        $purchaseReturn = DB::transaction(function () use ($data) {
            // Lock the purchase so two returns cannot take the same quantity at once
            $purchase = Purchase::lockForUpdate()->findOrFail($data['purchase_id']);
            abort_if($purchase->status === 'cancelled', 409, 'A cancelled purchase cannot have returns.');

            if ($data['return_date'] < $purchase->purchase_date->format('Y-m-d')) {
                throw ValidationException::withMessages(['return_date' => ['The return date cannot be before the purchase date.']]);
            }

            $boughtItems = $purchase->items()->get()->keyBy('product_id');
            $returned = $this->returnedQuantities($purchase->id);
            $products = $this->lockProducts(collect($data['items'])->pluck('product_id'));

            $subtotalOfPurchase = (float) $purchase->subtotal;
            $ratio = $subtotalOfPurchase > 0 ? (float) $purchase->total / $subtotalOfPurchase : 1;

            $subtotal = $total = 0;
            $lines = [];
            $changes = [];

            foreach ($data['items'] as $index => $item) {
                $boughtItem = $boughtItems->get((int) $item['product_id']);

                if (! $boughtItem) {
                    throw ValidationException::withMessages(["items.{$index}.product_id" => ['This product is not on the original purchase.']]);
                }

                $quantity = round((float) $item['quantity'], 2);
                $remaining = round((float) $boughtItem->quantity - (float) ($returned[$boughtItem->product_id] ?? 0), 2);

                if ($quantity > $remaining) {
                    throw ValidationException::withMessages(["items.{$index}.quantity" => ["Only {$remaining} can still be returned."]]);
                }

                // The goods leave the shop, so the stock must be there
                $product = $products[$boughtItem->product_id];
                $inStock = round((float) $product->stock_quantity, 2);

                if ($quantity > $inStock) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => ["Only {$inStock} of {$product->name} is in stock. The rest has already been sold."],
                    ]);
                }

                $gross = round($quantity * (float) $boughtItem->unit_cost, 2);
                $net = round($gross * $ratio, 2); // the purchase discount is shared across returned items

                $subtotal += $gross;
                $total += $net;

                $lines[] = [
                    'product_id' => $boughtItem->product_id,
                    'quantity' => $quantity,
                    'unit_cost' => $boughtItem->unit_cost,
                    'line_total' => $net,
                ];
                $changes[$boughtItem->product_id] = -$quantity;
            }

            $subtotal = round($subtotal, 2);
            $total = round($total, 2);
            $discount = round($subtotal - $total, 2);

            // Work out how the supplier settles this return
            [$maxDue, $maxCash] = $this->limits($purchase);

            if ($data['refund_method'] === 'adjust_due' && ! $purchase->supplier_id) {
                throw ValidationException::withMessages(['refund_method' => ['A one-time supplier return can only be settled in cash.']]);
            }

            $dueAdjustment = $data['refund_method'] === 'adjust_due' ? min($total, $maxDue) : 0;
            $cashReceived = round($total - $dueAdjustment, 2);

            if ($cashReceived > $maxCash + 0.004) {
                throw ValidationException::withMessages([
                    'refund_method' => [
                        'The cash to receive (' . number_format($cashReceived, 2) . ') is more than you have paid for this purchase ('
                        . number_format($maxCash, 2) . '). Choose "Adjust against due" instead.',
                    ],
                ]);
            }

            $purchaseReturn = PurchaseReturn::create([
                'purchase_id' => $purchase->id,
                'supplier_id' => $purchase->supplier_id,
                'supplier_name' => $purchase->supplier_name,
                'supplier_phone' => $purchase->supplier_phone,
                'return_date' => $data['return_date'],
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'due_adjustment' => $dueAdjustment,
                'cash_received' => $cashReceived,
                'refund_method' => $data['refund_method'],
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $purchaseReturn->update(['reference_no' => 'PRN-' . str_pad((string) $purchaseReturn->id, 5, '0', STR_PAD_LEFT)]);

            foreach ($lines as $line) {
                $purchaseReturn->items()->create($line);
            }
            $this->applyStock($products, $changes, 'purchase_return', $purchaseReturn);

            return $purchaseReturn;
        });

        return response()->json([
            'success' => true,
            'message' => 'Purchase return created successfully.',
            'data' => $purchaseReturn->load(['purchase:id,reference_no', 'items.product:id,name,sku']),
        ], 201);
    }

    public function show(PurchaseReturn $purchaseReturn): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Purchase return fetched successfully.',
            'data' => $purchaseReturn->load([
                'purchase:id,reference_no,purchase_date',
                'items.product:id,name,sku,unit_id',
                'items.product.unit:id,short_name',
            ]),
        ]);
    }

    public function cancel(Request $request, PurchaseReturn $purchaseReturn): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        DB::transaction(function () use ($purchaseReturn, $validated) {
            $purchaseReturn = PurchaseReturn::lockForUpdate()->findOrFail($purchaseReturn->id);
            abort_if($purchaseReturn->status === 'cancelled', 409, 'This return is already cancelled.');

            // Cancelling puts the returned goods back into stock
            $items = $purchaseReturn->items()->get();
            $changes = [];
            foreach ($items as $item) {
                $changes[$item->product_id] = ($changes[$item->product_id] ?? 0) + (float) $item->quantity;
            }
            $this->applyStock(
                $this->lockProducts($items->pluck('product_id')),
                $changes,
                'purchase_return_cancel',
                $purchaseReturn,
            );

            $purchaseReturn->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $validated['reason'],
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Purchase return cancelled successfully.',
            'data' => $purchaseReturn->refresh(),
        ]);
    }

    /**
     * Quantity already returned per product (cancelled returns are not counted).
     */
    private function returnedQuantities(int $purchaseId): Collection
    {
        return PurchaseReturnItem::query()
            ->join('purchase_returns', 'purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
            ->where('purchase_returns.purchase_id', $purchaseId)
            ->where('purchase_returns.status', 'completed')
            ->whereNull('purchase_returns.deleted_at')
            ->groupBy('purchase_return_items.product_id')
            ->selectRaw('purchase_return_items.product_id as product_id, SUM(purchase_return_items.quantity) as qty')
            ->pluck('qty', 'product_id');
    }

    /**
     * Returns [max amount that can still reduce what we owe, max cash that can still come back from the supplier].
     */
    private function limits(Purchase $purchase): array
    {
        $totals = PurchaseReturn::where('purchase_id', $purchase->id)
            ->where('status', 'completed')
            ->selectRaw('COALESCE(SUM(due_adjustment), 0) as due_adjusted, COALESCE(SUM(cash_received), 0) as cash_received')
            ->first();

        $maxDue = $purchase->supplier_id
            ? max(round((float) $purchase->total - (float) $purchase->paid_amount - (float) $totals->due_adjusted, 2), 0)
            : 0;
        $maxCash = max(round((float) $purchase->paid_amount - (float) $totals->cash_received, 2), 0);

        return [$maxDue, $maxCash];
    }
}