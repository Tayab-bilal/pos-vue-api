<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Supplier;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Builds the statement of a customer or a supplier: every entry in date order with a running balance.
 *
 * Customer side: Debit increases what the customer owes us (sale), Credit decreases it (payment, return).
 * Supplier side: Credit increases what we owe the supplier (purchase), Debit decreases it (payment, return).
 * Cancelled documents are left out, exactly like in PartyBalance.
 */
class LedgerService
{
    public static function customer(Customer $customer, ?string $from = null, ?string $to = null): array
    {
        $entries = collect();

        $sales = Sale::where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->withSum(['paymentAllocations as allocated' => fn ($q) => $q->whereHas('payment', fn ($p) => $p->where('status', 'completed'))], 'amount')
            ->get();

        foreach ($sales as $sale) {
            $entries->push(self::entry($sale->sale_date, $sale->created_at, 1, 'sale', 'Sale', $sale->reference_no, 'sale', $sale->id, (float) $sale->total, 0));

            // Money paid when the sale was made = total paid minus what later receipts added
            $atSale = round((float) $sale->paid_amount - (float) $sale->allocated, 2);
            if ($atSale > 0.004) {
                $entries->push(self::entry($sale->sale_date, $sale->created_at, 2, 'sale_payment', 'Paid at sale', $sale->reference_no, 'sale', $sale->id, 0, $atSale));
            }
        }

        $receipts = Payment::where('type', 'receipt')
            ->where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->get();

        foreach ($receipts as $receipt) {
            $entries->push(self::entry(
                $receipt->payment_date, $receipt->created_at, 3, 'receipt', 'Receipt', $receipt->reference_no, 'payment', $receipt->id,
                0, (float) $receipt->amount, self::methodLabel($receipt->method, $receipt->transaction_ref),
            ));
        }

        $returns = SaleReturn::where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->with('sale:id,reference_no')
            ->get();

        foreach ($returns as $return) {
            $against = 'Against ' . ($return->sale?->reference_no ?? '-');
            $entries->push(self::entry($return->return_date, $return->created_at, 4, 'sale_return', 'Sales return', $return->reference_no, 'sale_return', $return->id, 0, (float) $return->total, $against));

            if ((float) $return->cash_refund > 0.004) {
                $entries->push(self::entry($return->return_date, $return->created_at, 5, 'cash_refund', 'Cash refunded', $return->reference_no, 'sale_return', $return->id, (float) $return->cash_refund, 0, $against));
            }
        }

        return array_merge([
            'side' => 'customer',
            'party' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'company_name' => $customer->company_name,
                'phone' => $customer->phone,
                'city' => $customer->city,
                'address' => $customer->address,
                'credit_limit' => (float) $customer->credit_limit,
            ],
            'current_outstanding' => PartyBalance::customerSummary($customer)['outstanding'],
        ], self::build('customer', (float) $customer->opening_balance, $entries, $from, $to));
    }

    public static function supplier(Supplier $supplier, ?string $from = null, ?string $to = null): array
    {
        $entries = collect();

        $purchases = Purchase::where('supplier_id', $supplier->id)
            ->where('status', 'completed')
            ->withSum(['paymentAllocations as allocated' => fn ($q) => $q->whereHas('payment', fn ($p) => $p->where('status', 'completed'))], 'amount')
            ->get();

        foreach ($purchases as $purchase) {
            $entries->push(self::entry($purchase->purchase_date, $purchase->created_at, 1, 'purchase', 'Purchase', $purchase->reference_no, 'purchase', $purchase->id, 0, (float) $purchase->total));

            // Money paid when the purchase was made = total paid minus what later payments added
            $atPurchase = round((float) $purchase->paid_amount - (float) $purchase->allocated, 2);
            if ($atPurchase > 0.004) {
                $entries->push(self::entry($purchase->purchase_date, $purchase->created_at, 2, 'purchase_payment', 'Paid at purchase', $purchase->reference_no, 'purchase', $purchase->id, $atPurchase, 0));
            }
        }

        $payments = Payment::where('type', 'payment')
            ->where('supplier_id', $supplier->id)
            ->where('status', 'completed')
            ->get();

        foreach ($payments as $payment) {
            $entries->push(self::entry(
                $payment->payment_date, $payment->created_at, 3, 'payment', 'Payment', $payment->reference_no, 'payment', $payment->id,
                (float) $payment->amount, 0, self::methodLabel($payment->method, $payment->transaction_ref),
            ));
        }

        $returns = PurchaseReturn::where('supplier_id', $supplier->id)
            ->where('status', 'completed')
            ->with('purchase:id,reference_no')
            ->get();

        foreach ($returns as $return) {
            $against = 'Against ' . ($return->purchase?->reference_no ?? '-');
            $entries->push(self::entry($return->return_date, $return->created_at, 4, 'purchase_return', 'Purchase return', $return->reference_no, 'purchase_return', $return->id, (float) $return->total, 0, $against));

            if ((float) $return->cash_received > 0.004) {
                $entries->push(self::entry($return->return_date, $return->created_at, 5, 'cash_received', 'Cash received', $return->reference_no, 'purchase_return', $return->id, 0, (float) $return->cash_received, $against));
            }
        }

        return array_merge([
            'side' => 'supplier',
            'party' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'company_name' => $supplier->company_name,
                'phone' => $supplier->phone,
                'city' => $supplier->city,
                'address' => $supplier->address,
                'credit_limit' => null,
            ],
            'current_outstanding' => PartyBalance::supplierSummary($supplier)['outstanding'],
        ], self::build('supplier', (float) $supplier->opening_balance, $entries, $from, $to));
    }

    /**
     * Sort the entries, work out the balance brought forward, the running balance and the totals.
     */
    private static function build(string $side, float $opening, Collection $entries, ?string $from, ?string $to): array
    {
        // How an entry moves the balance: a customer's balance grows with debits, a supplier's with credits
        $effect = fn (array $e): float => $side === 'customer' ? $e['debit'] - $e['credit'] : $e['credit'] - $e['debit'];

        $sorted = $entries->sortBy([['date', 'asc'], ['created', 'asc'], ['order', 'asc']])->values();

        $forward = round($opening, 2);
        $rows = [];

        foreach ($sorted as $entry) {
            if ($from && $entry['date'] < $from) {
                $forward = round($forward + $effect($entry), 2);
                continue;
            }
            if ($to && $entry['date'] > $to) {
                continue;
            }
            $rows[] = $entry;
        }

        $balance = $forward;
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($rows as &$row) {
            $balance = round($balance + $effect($row), 2);
            $row['balance'] = $balance;
            $totalDebit += $row['debit'];
            $totalCredit += $row['credit'];
            unset($row['created'], $row['order']);
        }
        unset($row);

        return [
            'from' => $from,
            'to' => $to,
            'opening_balance' => $forward, // balance brought forward to the first row
            'entries' => $rows,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'closing_balance' => $balance,
        ];
    }

    private static function entry(
        CarbonInterface $date,
        CarbonInterface $createdAt,
        int $order,
        string $type,
        string $label,
        ?string $reference,
        ?string $documentType,
        ?int $documentId,
        float $debit,
        float $credit,
        ?string $note = null,
    ): array {
        return [
            'date' => $date->format('Y-m-d'),
            'created' => $createdAt->format('Y-m-d H:i:s'),
            'order' => $order,
            'type' => $type,
            'label' => $label,
            'reference_no' => $reference,
            'document_type' => $documentType,
            'document_id' => $documentId,
            'note' => $note,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
        ];
    }

    private static function methodLabel(?string $method, ?string $reference): string
    {
        $label = ucfirst(str_replace('_', ' ', (string) $method));

        return $reference ? "{$label} ({$reference})" : $label;
    }
}