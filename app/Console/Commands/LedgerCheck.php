<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Supplier;
use App\Services\LedgerService;
use App\Services\PartyBalance;
use Illuminate\Console\Command;

class LedgerCheck extends Command
{
    protected $signature = 'ledger:check';

    protected $description = 'Check that every ledger closing balance equals the outstanding balance (read only, changes nothing)';

    public function handle(): int
    {
        $rows = [];
        $problems = 0;

        foreach (Customer::withTrashed()->orderBy('id')->get() as $customer) {
            $ledger = LedgerService::customer($customer)['closing_balance'];
            $outstanding = PartyBalance::customerSummary($customer)['outstanding'];
            $match = abs($ledger - $outstanding) < 0.005;
            $problems += $match ? 0 : 1;
            $rows[] = ['Customer', $customer->id, $customer->name, number_format($ledger, 2), number_format($outstanding, 2), $match ? 'yes' : 'NO'];
        }

        foreach (Supplier::withTrashed()->orderBy('id')->get() as $supplier) {
            $ledger = LedgerService::supplier($supplier)['closing_balance'];
            $outstanding = PartyBalance::supplierSummary($supplier)['outstanding'];
            $match = abs($ledger - $outstanding) < 0.005;
            $problems += $match ? 0 : 1;
            $rows[] = ['Supplier', $supplier->id, $supplier->name, number_format($ledger, 2), number_format($outstanding, 2), $match ? 'yes' : 'NO'];
        }

        $this->info('Ledger check: the ledger closing balance must equal the outstanding balance.');
        $this->table(['Type', 'ID', 'Name', 'Ledger closing', 'Outstanding', 'Match'], $rows);

        if ($problems > 0) {
            $this->warn("{$problems} ledger(s) do not match.");
        } else {
            $this->info('Every ledger matches its outstanding balance.');
        }

        return self::SUCCESS;
    }
}