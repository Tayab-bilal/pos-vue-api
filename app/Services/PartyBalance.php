<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Supplier;
use Illuminate\Support\Collection;

/**
 * One place that works out what a customer owes us, or what we owe a supplier.
 *
 * Document due = total - paid_amount - due adjusted by completed returns.
 * Opening due  = opening balance - opening part of completed payments.
 */
class PartyBalance
{
    public static function customerOpeningOutstanding(Customer $customer): float
    {
        return max(round((float) $customer->opening_balance - self::openingPaid('receipt', 'customer_id', $customer->id), 2), 0);
    }

    public static function supplierOpeningOutstanding(Supplier $supplier): float
    {
        return max(round((float) $supplier->opening_balance - self::openingPaid('payment', 'supplier_id', $supplier->id), 2), 0);
    }

    public static function saleReturnAdjustments(int $saleId): float
    {
        return (float) SaleReturn::where('sale_id', $saleId)->where('status', 'completed')->sum('due_adjustment');
    }

    public static function purchaseReturnAdjustments(int $purchaseId): float
    {
        return (float) PurchaseReturn::where('purchase_id', $purchaseId)->where('status', 'completed')->sum('due_adjustment');
    }

    /**
     * Completed sales of a customer that still have something due, oldest first.
     */
    public static function customerInvoices(Customer $customer): Collection
    {
        return Sale::where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->withSum(['returns as returns_adjusted' => fn ($q) => $q->where('status', 'completed')], 'due_adjustment')
            ->orderBy('sale_date')
            ->orderBy('id')
            ->get()
            ->map(fn ($sale) => self::row($sale, $sale->sale_date, (float) $sale->returns_adjusted))
            ->filter(fn ($row) => $row['due'] > 0.004)
            ->values();
    }

    /**
     * Completed purchases from a supplier that still have something due, oldest first.
     */
    public static function supplierInvoices(Supplier $supplier): Collection
    {
        return Purchase::where('supplier_id', $supplier->id)
            ->where('status', 'completed')
            ->withSum(['returns as returns_adjusted' => fn ($q) => $q->where('status', 'completed')], 'due_adjustment')
            ->orderBy('purchase_date')
            ->orderBy('id')
            ->get()
            ->map(fn ($purchase) => self::row($purchase, $purchase->purchase_date, (float) $purchase->returns_adjusted))
            ->filter(fn ($row) => $row['due'] > 0.004)
            ->values();
    }

    public static function customerSummary(Customer $customer): array
    {
        $sales = Sale::where('customer_id', $customer->id)->where('status', 'completed')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total, COALESCE(SUM(paid_amount), 0) as paid')
            ->first();
        $returns = SaleReturn::where('customer_id', $customer->id)->where('status', 'completed')
            ->selectRaw('COALESCE(SUM(total), 0) as total, COALESCE(SUM(due_adjustment), 0) as adjusted')
            ->first();

        $openingPaid = self::openingPaid('receipt', 'customer_id', $customer->id);
        $openingOutstanding = self::customerOpeningOutstanding($customer);

        return [
            'opening_balance' => round((float) $customer->opening_balance, 2),
            'opening_outstanding' => $openingOutstanding,
            'documents_count' => (int) $sales->count,
            'documents_total' => round((float) $sales->total, 2),
            'returns_total' => round((float) $returns->total, 2),
            'paid_total' => round((float) $sales->paid + $openingPaid, 2),
            'outstanding' => round($openingOutstanding + (float) $sales->total - (float) $sales->paid - (float) $returns->adjusted, 2),
        ];
    }

    public static function supplierSummary(Supplier $supplier): array
    {
        $purchases = Purchase::where('supplier_id', $supplier->id)->where('status', 'completed')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total, COALESCE(SUM(paid_amount), 0) as paid')
            ->first();
        $returns = PurchaseReturn::where('supplier_id', $supplier->id)->where('status', 'completed')
            ->selectRaw('COALESCE(SUM(total), 0) as total, COALESCE(SUM(due_adjustment), 0) as adjusted')
            ->first();

        $openingPaid = self::openingPaid('payment', 'supplier_id', $supplier->id);
        $openingOutstanding = self::supplierOpeningOutstanding($supplier);

        return [
            'opening_balance' => round((float) $supplier->opening_balance, 2),
            'opening_outstanding' => $openingOutstanding,
            'documents_count' => (int) $purchases->count,
            'documents_total' => round((float) $purchases->total, 2),
            'returns_total' => round((float) $returns->total, 2),
            'paid_total' => round((float) $purchases->paid + $openingPaid, 2),
            'outstanding' => round($openingOutstanding + (float) $purchases->total - (float) $purchases->paid - (float) $returns->adjusted, 2),
        ];
    }

    /**
     * Outstanding balance of many customers at once, for list pages: [customer_id => amount].
     * Uses the same formula as customerSummary(), but with only 3 queries for the whole page.
     */
    public static function customerOutstandingMap(Collection $customers): array
    {
        $ids = $customers->pluck('id')->all();
        if (! $ids) {
            return [];
        }

        $due = Sale::whereIn('customer_id', $ids)->where('status', 'completed')
            ->groupBy('customer_id')
            ->selectRaw('customer_id, SUM(total - paid_amount) as amount')
            ->pluck('amount', 'customer_id');

        $adjusted = SaleReturn::whereIn('customer_id', $ids)->where('status', 'completed')
            ->groupBy('customer_id')
            ->selectRaw('customer_id, SUM(due_adjustment) as amount')
            ->pluck('amount', 'customer_id');

        $openingPaid = Payment::where('type', 'receipt')->whereIn('customer_id', $ids)->where('status', 'completed')
            ->groupBy('customer_id')
            ->selectRaw('customer_id, SUM(opening_amount) as amount')
            ->pluck('amount', 'customer_id');

        $map = [];
        foreach ($customers as $customer) {
            $opening = max(round((float) $customer->opening_balance - (float) ($openingPaid[$customer->id] ?? 0), 2), 0);
            $map[$customer->id] = round($opening + (float) ($due[$customer->id] ?? 0) - (float) ($adjusted[$customer->id] ?? 0), 2);
        }

        return $map;
    }

    /**
     * Outstanding balance of many suppliers at once, for list pages: [supplier_id => amount].
     */
    public static function supplierOutstandingMap(Collection $suppliers): array
    {
        $ids = $suppliers->pluck('id')->all();
        if (! $ids) {
            return [];
        }

        $due = Purchase::whereIn('supplier_id', $ids)->where('status', 'completed')
            ->groupBy('supplier_id')
            ->selectRaw('supplier_id, SUM(total - paid_amount) as amount')
            ->pluck('amount', 'supplier_id');

        $adjusted = PurchaseReturn::whereIn('supplier_id', $ids)->where('status', 'completed')
            ->groupBy('supplier_id')
            ->selectRaw('supplier_id, SUM(due_adjustment) as amount')
            ->pluck('amount', 'supplier_id');

        $openingPaid = Payment::where('type', 'payment')->whereIn('supplier_id', $ids)->where('status', 'completed')
            ->groupBy('supplier_id')
            ->selectRaw('supplier_id, SUM(opening_amount) as amount')
            ->pluck('amount', 'supplier_id');

        $map = [];
        foreach ($suppliers as $supplier) {
            $opening = max(round((float) $supplier->opening_balance - (float) ($openingPaid[$supplier->id] ?? 0), 2), 0);
            $map[$supplier->id] = round($opening + (float) ($due[$supplier->id] ?? 0) - (float) ($adjusted[$supplier->id] ?? 0), 2);
        }

        return $map;
    }

    private static function openingPaid(string $type, string $column, int $partyId): float
    {
        return (float) Payment::where('type', $type)
            ->where($column, $partyId)
            ->where('status', 'completed')
            ->sum('opening_amount');
    }

    private static function row($document, $date, float $adjusted): array
    {
        return [
            'id' => $document->id,
            'reference_no' => $document->reference_no,
            'date' => $date->format('Y-m-d'),
            'total' => round((float) $document->total, 2),
            'paid' => round((float) $document->paid_amount, 2),
            'returns_adjusted' => round($adjusted, 2),
            'due' => round((float) $document->total - (float) $document->paid_amount - $adjusted, 2),
        ];
    }
}
