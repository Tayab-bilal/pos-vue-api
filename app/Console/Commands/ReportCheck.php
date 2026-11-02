<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ReportService;
use Illuminate\Console\Command;

class ReportCheck extends Command
{
    protected $signature = 'report:check {--from= : Start date (YYYY-MM-DD)} {--to= : End date (YYYY-MM-DD)}';

    protected $description = 'Check that the report figures agree with each other (read only, changes nothing)';

    public function handle(): int
    {
        $from = $this->option('from') ?: null;
        $to = $this->option('to') ?: null;

        $sales = ReportService::sales($from, $to);
        $profitLoss = ReportService::profitLoss($from, $to);
        $products = ReportService::productProfit($from, $to);
        $expenses = ReportService::expenses($from, $to);
        $stock = ReportService::stockValuation();

        // Each product figure is rounded, so allow a cent of difference per product
        $tolerance = 0.01 * max(1, count($products));

        $stockDirect = (float) Product::query()->selectRaw('COALESCE(SUM(stock_quantity * cost_price), 0) as v')->value('v');

        $checks = [
            ['Net sales: sales report vs profit and loss', $sales['summary']['net_sales'], $profitLoss['totals']['net_sales'], 0.02],
            ['Net sales: daily rows added up vs profit and loss', array_sum(array_column($sales['daily'], 'net')), $profitLoss['totals']['net_sales'], 0.02],
            ['Net sales: product revenue added up vs profit and loss', array_sum(array_column($products, 'revenue')), $profitLoss['totals']['net_sales'], $tolerance],
            ['Cost of goods: product cost added up vs profit and loss', array_sum(array_column($products, 'cost')), $profitLoss['totals']['cogs'], $tolerance],
            ['Net profit: monthly rows added up vs total', array_sum(array_column($profitLoss['monthly'], 'net_profit')), $profitLoss['totals']['net_profit'], 0.01 * max(1, count($profitLoss['monthly']))],
            ['Expenses: expenses report vs profit and loss', $expenses['summary']['total'], $profitLoss['totals']['expenses'], 0.02],
            ['Stock value: valuation report vs products table', $stock['totals']['cost_value'], $stockDirect, 0.02],
            ['Stock value: categories added up vs total', array_sum(array_column($stock['categories'], 'cost_value')), $stock['totals']['cost_value'], 0.01 * max(1, count($stock['categories']))],
        ];

        $rows = [];
        $problems = 0;

        foreach ($checks as [$label, $a, $b, $allowed]) {
            $match = abs($a - $b) <= $allowed;
            $problems += $match ? 0 : 1;
            $rows[] = [$label, number_format($a, 2), number_format($b, 2), $match ? 'yes' : 'NO'];
        }

        $this->info('Report check' . ($from || $to ? " ({$from} to {$to})" : ' (all time)'));
        $this->table(['Check', 'First figure', 'Second figure', 'Match'], $rows);

        if ($problems > 0) {
            $this->warn("{$problems} check(s) do not match.");
        } else {
            $this->info('All report figures agree.');
        }

        return self::SUCCESS;
    }
}