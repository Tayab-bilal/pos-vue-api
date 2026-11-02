<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait ManagesStock
{
    /**
     * Lock the product rows so two requests cannot change the same stock at once.
     */
    protected function lockProducts(Collection $productIds): Collection
    {
        return Product::withTrashed()
            ->whereIn('id', $productIds->unique()->values())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * Apply stock changes (positive = add, negative = remove) and record each one in stock_movements.
     * Stock can never go below zero. Must be called inside a database transaction.
     *
     * $type      what happened, e.g. 'purchase', 'sale_cancel'
     * $reference the document that caused it (its reference_no is copied to the history)
     * $shortage  error text when stock would go negative (:product is replaced with the product name)
     */
    protected function applyStock(
        Collection $products,
        array $changes,
        string $type,
        ?Model $reference = null,
        ?string $note = null,
        string $shortage = 'Not enough stock of :product.',
    ): void {
        foreach ($changes as $productId => $change) {
            if (abs($change) < 0.005) {
                continue;
            }

            $product = $products[$productId];
            $newStock = round((float) $product->stock_quantity + $change, 2);

            if ($newStock < 0) {
                throw ValidationException::withMessages([
                    'items' => [str_replace(':product', $product->name, $shortage)],
                ]);
            }

            $product->stock_quantity = $newStock;
            $product->save();

            StockMovement::create([
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => round($change, 2),
                'balance_after' => $newStock,
                'reference_type' => $reference ? Str::snake(class_basename($reference)) : null,
                'reference_id' => $reference?->getKey(),
                'reference_no' => $reference->reference_no ?? null,
                'note' => $note,
            ]);
        }
    }
}