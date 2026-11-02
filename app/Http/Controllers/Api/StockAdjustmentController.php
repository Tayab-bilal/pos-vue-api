<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ManagesStock;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockAdjustmentRequest;
use App\Models\StockAdjustment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAdjustmentController extends Controller
{
    use ManagesStock;

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 10), 100);

        $adjustments = StockAdjustment::query()
            ->withCount('items')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('reference_no', 'like', "%{$search}%")
                      ->orWhere('reason', 'like', "%{$search}%")
                      ->orWhereHas('items.product', fn ($p) => $p->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('adjustment_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('adjustment_date', '<=', $request->input('date_to')))
            ->orderByDesc('adjustment_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Stock adjustments fetched successfully.',
            'data' => $adjustments,
        ]);
    }

    public function store(StockAdjustmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $adjustment = DB::transaction(function () use ($data) {
            // Lock the products so the stock we read cannot change while we work
            $products = $this->lockProducts(collect($data['items'])->pluck('product_id'));

            $decreaseOnly = in_array($data['type'], StockAdjustment::DECREASE_ONLY, true);
            $typeLabel = str_replace('_', ' ', $data['type']);

            $lines = [];
            $changes = [];
            $valueChange = 0;

            foreach ($data['items'] as $index => $item) {
                $product = $products[(int) $item['product_id']];
                $before = round((float) $product->stock_quantity, 2);

                $counted = $item['counted_quantity'] ?? null;
                $change = $item['quantity_change'] ?? null;

                if ($counted !== null) {
                    // Physical count: the system works out the difference
                    $field = 'counted_quantity';
                    $after = round((float) $counted, 2);
                    $difference = round($after - $before, 2);
                } elseif ($change !== null) {
                    $field = 'quantity_change';
                    $difference = round((float) $change, 2);
                    $after = round($before + $difference, 2);
                } else {
                    throw ValidationException::withMessages([
                        "items.{$index}.counted_quantity" => ['Enter the counted quantity or the change in stock.'],
                    ]);
                }

                if (abs($difference) < 0.005) {
                    throw ValidationException::withMessages([
                        "items.{$index}.{$field}" => ["This does not change the stock of {$product->name} (currently {$before})."],
                    ]);
                }

                if ($after < 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.{$field}" => ["Stock of {$product->name} cannot go below zero (currently {$before})."],
                    ]);
                }

                if ($decreaseOnly && $difference > 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.{$field}" => ["A {$typeLabel} adjustment can only reduce stock."],
                    ]);
                }

                $lines[] = [
                    'product_id' => $product->id,
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'difference' => $difference,
                    'unit_cost' => $product->cost_price,
                ];
                $changes[$product->id] = $difference;
                $valueChange += $difference * (float) $product->cost_price;
            }

            $adjustment = StockAdjustment::create([
                'adjustment_date' => $data['adjustment_date'],
                'type' => $data['type'],
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'value_change' => round($valueChange, 2),
            ]);

            $adjustment->update(['reference_no' => 'ADJ-' . str_pad((string) $adjustment->id, 5, '0', STR_PAD_LEFT)]);

            foreach ($lines as $line) {
                $adjustment->items()->create($line);
            }
            $this->applyStock($products, $changes, 'adjustment', $adjustment, $data['reason'] ?? null);

            return $adjustment;
        });

        return response()->json([
            'success' => true,
            'message' => 'Stock adjustment created successfully.',
            'data' => $adjustment->load(['items.product:id,name,sku']),
        ], 201);
    }

    public function show(StockAdjustment $stockAdjustment): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Stock adjustment fetched successfully.',
            'data' => $stockAdjustment->load([
                'items.product:id,name,sku,unit_id',
                'items.product.unit:id,short_name',
            ]),
        ]);
    }

    public function cancel(Request $request, StockAdjustment $stockAdjustment): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        DB::transaction(function () use ($stockAdjustment, $validated) {
            $adjustment = StockAdjustment::lockForUpdate()->findOrFail($stockAdjustment->id);
            abort_if($adjustment->status === 'cancelled', 409, 'This adjustment is already cancelled.');

            $items = $adjustment->items()->get();
            $products = $this->lockProducts($items->pluck('product_id'));

            // Cancelling undoes the change: what was added is taken back, what was removed is added back
            $changes = [];
            foreach ($items as $item) {
                $changes[$item->product_id] = ($changes[$item->product_id] ?? 0) - (float) $item->difference;
            }

            $this->applyStock(
                $products,
                $changes,
                'adjustment_cancel',
                $adjustment,
                shortage: 'Cannot cancel this adjustment: the stock of :product has already been used since.',
            );

            $adjustment->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $validated['reason'],
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Stock adjustment cancelled successfully.',
            'data' => $stockAdjustment->refresh(),
        ]);
    }
}