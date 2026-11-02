<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Console\Command;

class StockStartHistory extends Command
{
    protected $signature = 'stock:start-history';

    protected $description = 'Write an opening balance movement for every product that has no stock history yet';

    public function handle(): int
    {
        $created = 0;

        foreach (Product::withTrashed()->get() as $product) {
            // Products that already have history are left alone, so this is safe to run again
            if (StockMovement::where('product_id', $product->id)->exists()) {
                continue;
            }

            $stock = (float) $product->stock_quantity;
            if ($stock <= 0) {
                continue;
            }

            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'opening',
                'quantity' => $stock,
                'balance_after' => $stock,
                'reference_type' => 'product',
                'reference_id' => $product->id,
                'reference_no' => $product->sku,
                'note' => 'Opening balance when stock history started',
            ]);
            $created++;
        }

        $this->info("Created {$created} opening movement(s).");

        return self::SUCCESS;
    }
}