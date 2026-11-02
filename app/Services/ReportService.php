<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * All report figures come from here, so reports, the dashboard and the checks use one set of formulas.
 * Cancelled documents are always left out. Dates are optional: no dates means all time.
 */
class ReportService
{
    // The invoice discount is shared out over its lines: line value x (invoice total / invoice subtotal)
    private const SALE_LINE_REVENUE = 'sale_items.line_total * CASE WHEN sales.subtotal > 0 THEN sales.total / sales.subtotal ELSE 1 END';

    private const PURCHASE_LINE_COST = 'purchase_items.line_total * CASE WHEN purchases.subtotal > 0 THEN purchases.total / purchases.subtotal ELSE 1 END';

    public static function sales(?string $from, ?string $to): array
    {
        $sales = fn (): Builder => self::range(
            DB::table('sales')->where('sales.status', 'completed')->whereNull('sales.deleted_at'),
            'sales.sale_date', $from, $to,
        );
        $returns = fn (): Builder => self::range(
            DB::table('sale_returns')->where('sale_returns.status', 'completed')->whereNull('sale_returns.deleted_at'),
            'sale_returns.return_date', $from, $to,
        );

        $totals = $sales()->selectRaw('COUNT(*) as count, COALESCE(SUM(sales.subtotal), 0) as gross, COALESCE(SUM(sales.discount), 0) as discount, COALESCE(SUM(sales.total), 0) as total')->first();
        $returned = $returns()->selectRaw('COUNT(*) as count, COALESCE(SUM(sale_returns.total), 0) as total')->first();

        $count = (int) $totals->count;
        $total = self::money($totals->total);
        $returnsTotal = self::money($returned->total);

        $byStatus = $sales()->groupBy('sales.payment_status')
            ->selectRaw('sales.payment_status as status, COUNT(*) as count, SUM(sales.total) as total')
            ->get()
            ->map(fn ($r) => ['status' => $r->status, 'count' => (int) $r->count, 'total' => self::money($r->total)])
            ->all();

        $daily = self::dailySeries(
            $sales()->groupBy('sales.sale_date')->selectRaw('sales.sale_date as on_date, COUNT(*) as count, SUM(sales.total) as total')->get(),
            $returns()->groupBy('sale_returns.return_date')->selectRaw('sale_returns.return_date as on_date, SUM(sale_returns.total) as total')->get(),
        );

        $topProducts = self::range(
            DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->where('sales.status', 'completed')->whereNull('sales.deleted_at'),
            'sales.sale_date', $from, $to,
        )
            ->groupBy('sale_items.product_id', 'products.name', 'products.sku')
            ->selectRaw('sale_items.product_id as id, products.name as name, products.sku as sku, SUM(sale_items.quantity) as quantity, SUM(' . self::SALE_LINE_REVENUE . ') as amount')
            ->orderByDesc('amount')->limit(10)->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name, 'sku' => $r->sku, 'quantity' => self::money($r->quantity), 'amount' => self::money($r->amount)])
            ->all();

        $topCustomers = $sales()->groupBy('sales.customer_id')
            ->selectRaw("sales.customer_id as id, CASE WHEN sales.customer_id IS NULL THEN 'Walk-in customers' ELSE MAX(sales.customer_name) END as name, COUNT(*) as count, SUM(sales.total) as total")
            ->orderByDesc('total')->limit(10)->get()
            ->map(fn ($r) => ['id' => $r->id ? (int) $r->id : null, 'name' => $r->name, 'count' => (int) $r->count, 'total' => self::money($r->total)])
            ->all();

        return [
            'from' => $from,
            'to' => $to,
            'summary' => [
                'count' => $count,
                'gross' => self::money($totals->gross),
                'discount' => self::money($totals->discount),
                'total' => $total,
                'returns_count' => (int) $returned->count,
                'returns_total' => $returnsTotal,
                'net_sales' => round($total - $returnsTotal, 2),
                'average' => $count > 0 ? round($total / $count, 2) : 0.0,
            ],
            'by_status' => $byStatus,
            'daily' => $daily,
            'top_products' => $topProducts,
            'top_customers' => $topCustomers,
        ];
    }

    public static function purchases(?string $from, ?string $to): array
    {
        $purchases = fn (): Builder => self::range(
            DB::table('purchases')->where('purchases.status', 'completed')->whereNull('purchases.deleted_at'),
            'purchases.purchase_date', $from, $to,
        );
        $returns = fn (): Builder => self::range(
            DB::table('purchase_returns')->where('purchase_returns.status', 'completed')->whereNull('purchase_returns.deleted_at'),
            'purchase_returns.return_date', $from, $to,
        );

        $totals = $purchases()->selectRaw('COUNT(*) as count, COALESCE(SUM(purchases.subtotal), 0) as gross, COALESCE(SUM(purchases.discount), 0) as discount, COALESCE(SUM(purchases.total), 0) as total')->first();
        $returned = $returns()->selectRaw('COUNT(*) as count, COALESCE(SUM(purchase_returns.total), 0) as total')->first();

        $count = (int) $totals->count;
        $total = self::money($totals->total);
        $returnsTotal = self::money($returned->total);

        $byStatus = $purchases()->groupBy('purchases.payment_status')
            ->selectRaw('purchases.payment_status as status, COUNT(*) as count, SUM(purchases.total) as total')
            ->get()
            ->map(fn ($r) => ['status' => $r->status, 'count' => (int) $r->count, 'total' => self::money($r->total)])
            ->all();

        $daily = self::dailySeries(
            $purchases()->groupBy('purchases.purchase_date')->selectRaw('purchases.purchase_date as on_date, COUNT(*) as count, SUM(purchases.total) as total')->get(),
            $returns()->groupBy('purchase_returns.return_date')->selectRaw('purchase_returns.return_date as on_date, SUM(purchase_returns.total) as total')->get(),
        );

        $topProducts = self::range(
            DB::table('purchase_items')
                ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
                ->join('products', 'products.id', '=', 'purchase_items.product_id')
                ->where('purchases.status', 'completed')->whereNull('purchases.deleted_at'),
            'purchases.purchase_date', $from, $to,
        )
            ->groupBy('purchase_items.product_id', 'products.name', 'products.sku')
            ->selectRaw('purchase_items.product_id as id, products.name as name, products.sku as sku, SUM(purchase_items.quantity) as quantity, SUM(' . self::PURCHASE_LINE_COST . ') as amount')
            ->orderByDesc('amount')->limit(10)->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name, 'sku' => $r->sku, 'quantity' => self::money($r->quantity), 'amount' => self::money($r->amount)])
            ->all();

        $topSuppliers = $purchases()->groupBy('purchases.supplier_id')
            ->selectRaw("purchases.supplier_id as id, CASE WHEN purchases.supplier_id IS NULL THEN 'One-time suppliers' ELSE MAX(purchases.supplier_name) END as name, COUNT(*) as count, SUM(purchases.total) as total")
            ->orderByDesc('total')->limit(10)->get()
            ->map(fn ($r) => ['id' => $r->id ? (int) $r->id : null, 'name' => $r->name, 'count' => (int) $r->count, 'total' => self::money($r->total)])
            ->all();

        return [
            'from' => $from,
            'to' => $to,
            'summary' => [
                'count' => $count,
                'gross' => self::money($totals->gross),
                'discount' => self::money($totals->discount),
                'total' => $total,
                'returns_count' => (int) $returned->count,
                'returns_total' => $returnsTotal,
                'net_purchases' => round($total - $returnsTotal, 2),
                'average' => $count > 0 ? round($total / $count, 2) : 0.0,
            ],
            'by_status' => $byStatus,
            'daily' => $daily,
            'top_products' => $topProducts,
            'top_suppliers' => $topSuppliers,
        ];
    }

    /**
     * Profit per product: what was sold less what came back, against what that stock cost.
     */
    public static function productProfit(?string $from, ?string $to): array
    {
        $sold = self::range(
            DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->where('sales.status', 'completed')->whereNull('sales.deleted_at'),
            'sales.sale_date', $from, $to,
        )
            ->groupBy('sale_items.product_id')
            ->selectRaw('sale_items.product_id as product_id, SUM(sale_items.quantity) as quantity, SUM(' . self::SALE_LINE_REVENUE . ') as revenue, SUM(sale_items.quantity * sale_items.unit_cost) as cost')
            ->get()->keyBy('product_id');

        // Returned goods that went back to stock give their cost back. Damaged goods keep their cost as a loss.
        $returned = self::range(
            DB::table('sale_return_items')
                ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
                ->where('sale_returns.status', 'completed')->whereNull('sale_returns.deleted_at'),
            'sale_returns.return_date', $from, $to,
        )
            ->groupBy('sale_return_items.product_id')
            ->selectRaw('sale_return_items.product_id as product_id, SUM(sale_return_items.quantity) as quantity, SUM(sale_return_items.line_total) as revenue, SUM(CASE WHEN sale_return_items.restock = 1 THEN sale_return_items.quantity * sale_return_items.unit_cost ELSE 0 END) as cost')
            ->get()->keyBy('product_id');

        $ids = $sold->keys()->merge($returned->keys())->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $products = DB::table('products')->whereIn('id', $ids->all())->get(['id', 'name', 'sku'])->keyBy('id');

        return $ids->map(function ($id) use ($sold, $returned, $products) {
            $s = $sold->get($id);
            $r = $returned->get($id);

            $quantity = (float) ($s->quantity ?? 0) - (float) ($r->quantity ?? 0);
            $revenue = (float) ($s->revenue ?? 0) - (float) ($r->revenue ?? 0);
            $cost = (float) ($s->cost ?? 0) - (float) ($r->cost ?? 0);
            $profit = $revenue - $cost;

            return [
                'id' => (int) $id,
                'name' => $products->get($id)->name ?? 'Unknown product',
                'sku' => $products->get($id)->sku ?? '',
                'quantity' => round($quantity, 2),
                'revenue' => round($revenue, 2),
                'cost' => round($cost, 2),
                'profit' => round($profit, 2),
                'margin' => abs($revenue) > 0.004 ? round($profit / $revenue * 100, 1) : null,
            ];
        })->sortByDesc('profit')->values()->all();
    }

    public static function profitLoss(?string $from, ?string $to): array
    {
        // Every figure is collected per month first, so the totals and the monthly rows always agree
        $months = [];
        $add = function (string $month, string $key, $value) use (&$months): void {
            $months[$month] ??= array_fill_keys(['gross', 'discount', 'sales', 'returns', 'cogs_sold', 'cogs_back', 'expenses', 'adjustments'], 0.0);
            $months[$month][$key] += (float) $value;
        };
        $monthOf = fn (string $column): string => "DATE_FORMAT({$column}, '%Y-%m')";

        $m = $monthOf('sales.sale_date');
        $rows = self::range(DB::table('sales')->where('sales.status', 'completed')->whereNull('sales.deleted_at'), 'sales.sale_date', $from, $to)
            ->groupByRaw($m)
            ->selectRaw("{$m} as ym, SUM(sales.subtotal) as gross, SUM(sales.discount) as discount, SUM(sales.total) as total")
            ->get();
        foreach ($rows as $row) {
            $add($row->ym, 'gross', $row->gross);
            $add($row->ym, 'discount', $row->discount);
            $add($row->ym, 'sales', $row->total);
        }

        $m = $monthOf('sale_returns.return_date');
        $rows = self::range(DB::table('sale_returns')->where('sale_returns.status', 'completed')->whereNull('sale_returns.deleted_at'), 'sale_returns.return_date', $from, $to)
            ->groupByRaw($m)
            ->selectRaw("{$m} as ym, SUM(sale_returns.total) as total")
            ->get();
        foreach ($rows as $row) {
            $add($row->ym, 'returns', $row->total);
        }

        $m = $monthOf('sales.sale_date');
        $rows = self::range(
            DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')->where('sales.status', 'completed')->whereNull('sales.deleted_at'),
            'sales.sale_date', $from, $to,
        )
            ->groupByRaw($m)
            ->selectRaw("{$m} as ym, SUM(sale_items.quantity * sale_items.unit_cost) as cost")
            ->get();
        foreach ($rows as $row) {
            $add($row->ym, 'cogs_sold', $row->cost);
        }

        $m = $monthOf('sale_returns.return_date');
        $rows = self::range(
            DB::table('sale_return_items')->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
                ->where('sale_returns.status', 'completed')->whereNull('sale_returns.deleted_at')->where('sale_return_items.restock', true),
            'sale_returns.return_date', $from, $to,
        )
            ->groupByRaw($m)
            ->selectRaw("{$m} as ym, SUM(sale_return_items.quantity * sale_return_items.unit_cost) as cost")
            ->get();
        foreach ($rows as $row) {
            $add($row->ym, 'cogs_back', $row->cost);
        }

        $m = $monthOf('expenses.expense_date');
        $rows = self::range(DB::table('expenses')->where('expenses.status', 'completed')->whereNull('expenses.deleted_at'), 'expenses.expense_date', $from, $to)
            ->groupByRaw($m)
            ->selectRaw("{$m} as ym, SUM(expenses.amount) as total")
            ->get();
        foreach ($rows as $row) {
            $add($row->ym, 'expenses', $row->total);
        }

        $m = $monthOf('stock_adjustments.adjustment_date');
        $rows = self::range(DB::table('stock_adjustments')->where('stock_adjustments.status', 'completed')->whereNull('stock_adjustments.deleted_at'), 'stock_adjustments.adjustment_date', $from, $to)
            ->groupByRaw($m)
            ->selectRaw("{$m} as ym, SUM(stock_adjustments.value_change) as total")
            ->get();
        foreach ($rows as $row) {
            $add($row->ym, 'adjustments', $row->total);
        }

        ksort($months);

        $monthly = [];
        foreach ($months as $month => $v) {
            $netSales = $v['sales'] - $v['returns'];
            $cogs = $v['cogs_sold'] - $v['cogs_back'];
            $grossProfit = $netSales - $cogs;

            $monthly[] = [
                'month' => $month,
                'net_sales' => round($netSales, 2),
                'cogs' => round($cogs, 2),
                'gross_profit' => round($grossProfit, 2),
                'expenses' => round($v['expenses'], 2),
                'stock_adjustments' => round($v['adjustments'], 2),
                'net_profit' => round($grossProfit - $v['expenses'] + $v['adjustments'], 2),
            ];
        }

        $sum = fn (string $key): float => array_sum(array_column($months, $key));
        $netSales = $sum('sales') - $sum('returns');
        $cogs = $sum('cogs_sold') - $sum('cogs_back');
        $grossProfit = $netSales - $cogs;
        $netProfit = $grossProfit - $sum('expenses') + $sum('adjustments');

        $products = self::productProfit($from, $to);

        return [
            'from' => $from,
            'to' => $to,
            'totals' => [
                'gross_sales' => round($sum('gross'), 2),
                'discounts' => round($sum('discount'), 2),
                'sales' => round($sum('sales'), 2),
                'returns' => round($sum('returns'), 2),
                'net_sales' => round($netSales, 2),
                'cogs' => round($cogs, 2),
                'gross_profit' => round($grossProfit, 2),
                'expenses' => round($sum('expenses'), 2),
                'stock_adjustments' => round($sum('adjustments'), 2),
                'net_profit' => round($netProfit, 2),
                'gross_margin' => $netSales > 0.004 ? round($grossProfit / $netSales * 100, 1) : null,
                'net_margin' => $netSales > 0.004 ? round($netProfit / $netSales * 100, 1) : null,
            ],
            'monthly' => $monthly,
            'products' => array_slice($products, 0, 200),
            'products_total' => count($products),
        ];
    }

    public static function expenses(?string $from, ?string $to): array
    {
        $base = fn (): Builder => self::range(
            DB::table('expenses')->where('expenses.status', 'completed')->whereNull('expenses.deleted_at'),
            'expenses.expense_date', $from, $to,
        );

        $totals = $base()->selectRaw('COUNT(*) as count, COALESCE(SUM(expenses.amount), 0) as total')->first();
        $total = self::money($totals->total);
        $percent = fn ($amount): float => $total > 0.004 ? round((float) $amount / $total * 100, 1) : 0.0;

        $byCategory = $base()
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->groupBy('expenses.expense_category_id', 'expense_categories.name')
            ->selectRaw('expenses.expense_category_id as id, expense_categories.name as name, COUNT(*) as count, SUM(expenses.amount) as total')
            ->orderByDesc('total')->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name, 'count' => (int) $r->count, 'total' => self::money($r->total), 'percent' => $percent($r->total)])
            ->all();

        $byMethod = $base()->groupBy('expenses.method')
            ->selectRaw('expenses.method as method, COUNT(*) as count, SUM(expenses.amount) as total')
            ->orderByDesc('total')->get()
            ->map(fn ($r) => ['method' => $r->method, 'count' => (int) $r->count, 'total' => self::money($r->total), 'percent' => $percent($r->total)])
            ->all();

        $daily = $base()->groupBy('expenses.expense_date')
            ->selectRaw('expenses.expense_date as on_date, COUNT(*) as count, SUM(expenses.amount) as total')
            ->orderBy('on_date')->get()
            ->map(fn ($r) => ['date' => $r->on_date, 'count' => (int) $r->count, 'total' => self::money($r->total)])
            ->all();

        return [
            'from' => $from,
            'to' => $to,
            'summary' => ['count' => (int) $totals->count, 'total' => $total],
            'by_category' => $byCategory,
            'by_method' => $byMethod,
            'daily' => $daily,
        ];
    }

    /**
     * What the stock in hand is worth right now, at cost and at selling price.
     */
    public static function stockValuation(): array
    {
        $products = fn (): Builder => DB::table('products')->whereNull('products.deleted_at');

        $totals = $products()->selectRaw('
            COUNT(*) as products,
            COALESCE(SUM(products.stock_quantity * products.cost_price), 0) as cost_value,
            COALESCE(SUM(products.stock_quantity * products.sale_price), 0) as retail_value,
            COALESCE(SUM(CASE WHEN products.stock_quantity <= 0 THEN 1 ELSE 0 END), 0) as out_of_stock,
            COALESCE(SUM(CASE WHEN products.stock_quantity > 0 AND products.stock_quantity <= products.alert_quantity THEN 1 ELSE 0 END), 0) as low_stock
        ')->first();

        $categories = $products()
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->groupBy('products.category_id', 'categories.name')
            ->selectRaw("products.category_id as id, COALESCE(categories.name, 'No category') as name, COUNT(*) as products, SUM(products.stock_quantity * products.cost_price) as cost_value, SUM(products.stock_quantity * products.sale_price) as retail_value")
            ->orderByDesc('cost_value')->get()
            ->map(fn ($r) => [
                'id' => $r->id ? (int) $r->id : null,
                'name' => $r->name,
                'products' => (int) $r->products,
                'cost_value' => self::money($r->cost_value),
                'retail_value' => self::money($r->retail_value),
                'potential_profit' => round((float) $r->retail_value - (float) $r->cost_value, 2),
            ])->all();

        $list = fn (Builder $query): array => $query
            ->join('units', 'units.id', '=', 'products.unit_id')
            ->orderBy('products.stock_quantity')->orderBy('products.name')->limit(50)
            ->get(['products.id', 'products.name', 'products.sku', 'products.stock_quantity', 'products.alert_quantity', 'units.short_name as unit'])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'name' => $r->name,
                'sku' => $r->sku,
                'stock' => self::money($r->stock_quantity),
                'alert' => self::money($r->alert_quantity),
                'unit' => $r->unit,
            ])->all();

        $costValue = self::money($totals->cost_value);
        $retailValue = self::money($totals->retail_value);

        return [
            'totals' => [
                'products' => (int) $totals->products,
                'cost_value' => $costValue,
                'retail_value' => $retailValue,
                'potential_profit' => round($retailValue - $costValue, 2),
                'out_of_stock' => (int) $totals->out_of_stock,
                'low_stock' => (int) $totals->low_stock,
            ],
            'categories' => $categories,
            'low_stock' => $list($products()->where('products.stock_quantity', '>', 0)->whereColumn('products.stock_quantity', '<=', 'products.alert_quantity')),
            'out_of_stock' => $list($products()->where('products.stock_quantity', '<=', 0)),
        ];
    }

    private static function range(Builder $query, string $column, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->where($column, '>=', $from))
            ->when($to, fn (Builder $q) => $q->where($column, '<=', $to));
    }

    /**
     * One row per day: documents (sales or purchases), their total, the returns of that day, and the net.
     */
    private static function dailySeries(Collection $documents, Collection $returns): array
    {
        $days = [];

        foreach ($documents as $row) {
            $days[$row->on_date] = ['date' => $row->on_date, 'count' => (int) $row->count, 'total' => self::money($row->total), 'returns' => 0.0];
        }

        foreach ($returns as $row) {
            $days[$row->on_date] ??= ['date' => $row->on_date, 'count' => 0, 'total' => 0.0, 'returns' => 0.0];
            $days[$row->on_date]['returns'] = self::money($row->total);
        }

        ksort($days);

        return array_values(array_map(fn (array $day) => $day + ['net' => round($day['total'] - $day['returns'], 2)], $days));
    }

    private static function money($value): float
    {
        return round((float) $value, 2);
    }
}