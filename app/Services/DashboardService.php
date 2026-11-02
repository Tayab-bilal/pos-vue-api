<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Everything the dashboard shows. It reuses ReportService and PartyBalance, so the dashboard,
 * the reports, the ledgers and the stock page always agree.
 */
class DashboardService
{
    public static function build(?string $today = null): array
    {
        // "Today" comes from the browser, so the day does not shift because of the server time zone
        $date = $today ? Carbon::parse($today) : Carbon::now();
        $day = $date->toDateString();
        $monthStart = $date->copy()->startOfMonth()->toDateString();
        $monthEnd = $date->copy()->endOfMonth()->toDateString();
        $weekStart = $date->copy()->subDays(6)->toDateString();

        $salesToday = ReportService::sales($day, $day)['summary'];
        $profitToday = ReportService::profitLoss($day, $day)['totals'];

        $salesMonth = ReportService::sales($monthStart, $monthEnd);
        $profitMonth = ReportService::profitLoss($monthStart, $monthEnd)['totals'];

        $week = collect(ReportService::sales($weekStart, $day)['daily'])->keyBy('date');
        $stock = ReportService::stockValuation();

        $customers = Customer::all();
        $suppliers = Supplier::all();
        $receivable = PartyBalance::customerOutstandingMap($customers);
        $payable = PartyBalance::supplierOutstandingMap($suppliers);

        // Net sales for each of the last 7 days, with a zero for days that had no sales
        $chart = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $date->copy()->subDays($i)->toDateString();
            $chart[] = ['date' => $d, 'net' => (float) ($week->get($d)['net'] ?? 0)];
        }

        // Out of stock products first, then low stock ones
        $alerts = array_merge(
            array_map(fn (array $item) => $item + ['level' => 'out'], array_slice($stock['out_of_stock'], 0, 6)),
            array_map(fn (array $item) => $item + ['level' => 'low'], array_slice($stock['low_stock'], 0, 6)),
        );

        $recentSales = Sale::where('status', 'completed')
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->limit(6)
            ->get(['id', 'reference_no', 'customer_id', 'customer_name', 'sale_date', 'total', 'payment_status'])
            ->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'reference_no' => $sale->reference_no,
                'customer_name' => $sale->customer_name,
                'walk_in' => $sale->customer_id === null,
                'date' => $sale->sale_date->format('Y-m-d'),
                'total' => (float) $sale->total,
                'payment_status' => $sale->payment_status,
            ])
            ->all();

        // A credit limit of 0 means no limit
        $overLimit = $customers
            ->filter(fn (Customer $c) => (float) $c->credit_limit > 0 && ($receivable[$c->id] ?? 0) > (float) $c->credit_limit)
            ->count();

        return [
            'date' => $day,
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
            'today' => [
                'sales_count' => $salesToday['count'],
                'sales_total' => $salesToday['total'],
                'returns_total' => $salesToday['returns_total'],
                'net_sales' => $salesToday['net_sales'],
                'gross_profit' => $profitToday['gross_profit'],
            ],
            'month' => [
                'sales_count' => $salesMonth['summary']['count'],
                'net_sales' => $salesMonth['summary']['net_sales'],
                'gross_profit' => $profitMonth['gross_profit'],
                'expenses' => $profitMonth['expenses'],
                'net_profit' => $profitMonth['net_profit'],
            ],
            'receivable' => [
                'total' => round(array_sum($receivable), 2),
                'parties' => count(array_filter($receivable, fn ($amount) => $amount > 0.004)),
                'over_limit' => $overLimit,
                'top' => self::topParties($customers, $receivable),
            ],
            'payable' => [
                'total' => round(array_sum($payable), 2),
                'parties' => count(array_filter($payable, fn ($amount) => $amount > 0.004)),
                'top' => self::topParties($suppliers, $payable),
            ],
            'stock' => [
                'products' => $stock['totals']['products'],
                'cost_value' => $stock['totals']['cost_value'],
                'retail_value' => $stock['totals']['retail_value'],
                'low_stock' => $stock['totals']['low_stock'],
                'out_of_stock' => $stock['totals']['out_of_stock'],
                'alerts' => array_slice($alerts, 0, 6),
            ],
            'counts' => [
                'customers' => $customers->count(),
                'suppliers' => $suppliers->count(),
            ],
            'chart' => $chart,
            'top_products' => array_slice($salesMonth['top_products'], 0, 5),
            'recent_sales' => $recentSales,
        ];
    }

    /**
     * The parties with the biggest balance, largest first.
     */
    private static function topParties(Collection $parties, array $balances, int $limit = 5): array
    {
        return $parties
            ->map(fn ($party) => [
                'id' => $party->id,
                'name' => $party->name,
                'phone' => $party->phone,
                'outstanding' => (float) ($balances[$party->id] ?? 0),
            ])
            ->filter(fn (array $row) => $row['outstanding'] > 0.004)
            ->sortByDesc('outstanding')
            ->take($limit)
            ->values()
            ->all();
    }
}