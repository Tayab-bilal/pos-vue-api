<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StockCheck extends Command
{
    protected $signature = 'stock:check';

    protected $description = 'Compare current stock with documents and the stock history (read only, changes nothing)';

    public function handle(): int
    {
        $purchased = DB::table('purchase_items')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchases.status', 'completed')
            ->whereNull('purchases.deleted_at')
            ->groupBy('purchase_items.product_id')
            ->selectRaw('purchase_items.product_id as product_id, SUM(purchase_items.quantity) as qty')
            ->pluck('qty', 'product_id');

        $sold = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed')
            ->whereNull('sales.deleted_at')
            ->groupBy('sale_items.product_id')
            ->selectRaw('sale_items.product_id as product_id, SUM(sale_items.quantity) as qty')
            ->pluck('qty', 'product_id');

        $restocked = DB::table('sale_return_items')
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->where('sale_returns.status', 'completed')
            ->where('sale_return_items.restock', true)
            ->whereNull('sale_returns.deleted_at')
            ->groupBy('sale_return_items.product_id')
            ->selectRaw('sale_return_items.product_id as product_id, SUM(sale_return_items.quantity) as qty')
            ->pluck('qty', 'product_id');

        $sentBack = DB::table('purchase_return_items')
            ->join('purchase_returns', 'purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
            ->where('purchase_returns.status', 'completed')
            ->whereNull('purchase_returns.deleted_at')
            ->groupBy('purchase_return_items.product_id')
            ->selectRaw('purchase_return_items.product_id as product_id, SUM(purchase_return_items.quantity) as qty')
            ->pluck('qty', 'product_id');

        $adjusted = DB::table('stock_adjustment_items')
            ->join('stock_adjustments', 'stock_adjustments.id', '=', 'stock_adjustment_items.stock_adjustment_id')
            ->where('stock_adjustments.status', 'completed')
            ->whereNull('stock_adjustments.deleted_at')
            ->groupBy('stock_adjustment_items.product_id')
            ->selectRaw('stock_adjustment_items.product_id as product_id, SUM(stock_adjustment_items.difference) as qty')
            ->pluck('qty', 'product_id');

        // The balance written by the most recent stock movement of each product
        $history = DB::table('stock_movements')
            ->whereIn('id', function ($query) {
                $query->from('stock_movements')->selectRaw('MAX(id)')->groupBy('product_id');
            })
            ->pluck('balance_after', 'product_id');

        $rows = [];
        $problems = 0;

        foreach (Product::withTrashed()->orderBy('id')->get() as $product) {
            $in = (float) ($purchased[$product->id] ?? 0);
            $out = (float) ($sold[$product->id] ?? 0);
            $back = (float) ($restocked[$product->id] ?? 0);
            $sent = (float) ($sentBack[$product->id] ?? 0);
            $adj = (float) ($adjusted[$product->id] ?? 0);

            $movement = $in - $out + $back - $sent + $adj;
            $current = (float) $product->stock_quantity;

            $ledger = $history->has($product->id) ? (float) $history[$product->id] : null;
            $match = $ledger !== null && abs($ledger - $current) < 0.005;
            if (! $match) {
                $problems++;
            }

            $rows[] = [
                $product->id,
                $product->name,
                number_format($current, 2),
                number_format($in, 2),
                number_format($out, 2),
                number_format($back, 2),
                number_format($sent, 2),
                number_format($adj, 2),
                number_format($current - $movement, 2),
                $ledger === null ? '-' : number_format($ledger, 2),
                $match ? 'yes' : 'NO',
            ];
        }

        $this->info('Stock check');
        $this->line('Opening = Current - (Purchased - Sold + Restocked - Sent back + Adjusted). It must never change.');
        $this->line('History = balance written by the last stock movement. It must equal Current.');
        $this->table(
            ['ID', 'Product', 'Current', 'Purch', 'Sold', 'Restock', 'SentBk', 'Adjust', 'Opening', 'History', 'Match'],
            $rows
        );

        if ($problems > 0) {
            $this->warn("{$problems} product(s) where the history does not match the current stock.");
        } else {
            $this->info('Stock history matches current stock for every product.');
        }

        return self::SUCCESS;
    }
}