<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Supplier;
use App\Services\PartyBalance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    /**
     * $type is 'receipt' (money from customers) or 'payment' (money to suppliers). The route supplies it.
     */
    public function index(Request $request, string $type): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 10), 100);
        $partyColumn = $type === 'receipt' ? 'customer_id' : 'supplier_id';

        $payments = Payment::query()
            ->where('type', $type)
            ->withCount('allocations')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('reference_no', 'like', "%{$search}%")
                      ->orWhere('party_name', 'like', "%{$search}%")
                      ->orWhere('transaction_ref', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('party_id'), fn ($q) => $q->where($partyColumn, $request->integer('party_id')))
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->input('method')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('payment_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('payment_date', '<=', $request->input('date_to')))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Payments fetched successfully.',
            'data' => $payments,
        ]);
    }

    public function store(PaymentRequest $request, string $type): JsonResponse
    {
        $data = $request->validated();
        $isReceipt = $type === 'receipt';

        $payment = DB::transaction(function () use ($data, $type, $isReceipt) {
            // Lock the party and the invoices so two payments cannot pay the same due twice
            $party = $isReceipt
                ? Customer::lockForUpdate()->findOrFail($data['customer_id'])
                : Supplier::lockForUpdate()->findOrFail($data['supplier_id']);

            $openingDue = $isReceipt
                ? PartyBalance::customerOpeningOutstanding($party)
                : PartyBalance::supplierOpeningOutstanding($party);
            $opening = round((float) ($data['opening_amount'] ?? 0), 2);

            if ($opening > $openingDue + 0.004) {
                throw ValidationException::withMessages([
                    'opening_amount' => ['Only ' . number_format($openingDue, 2) . ' of the opening balance is still outstanding.'],
                ]);
            }

            $requested = collect($data['allocations'] ?? [])->values();
            $ids = $requested->pluck('document_id')->map(fn ($id) => (int) $id)->all();

            $documents = $isReceipt
                ? Sale::where('customer_id', $party->id)->where('status', 'completed')->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id')
                : Purchase::where('supplier_id', $party->id)->where('status', 'completed')->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

            $total = $opening;
            $lines = [];

            foreach ($requested as $index => $row) {
                $document = $documents->get((int) $row['document_id']);

                if (! $document) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.document_id" => ['This invoice is not available for payment.'],
                    ]);
                }

                $adjusted = $isReceipt
                    ? PartyBalance::saleReturnAdjustments($document->id)
                    : PartyBalance::purchaseReturnAdjustments($document->id);
                $due = round((float) $document->total - (float) $document->paid_amount - $adjusted, 2);
                $amount = round((float) $row['amount'], 2);

                if ($amount > $due + 0.004) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.amount" => ['Only ' . number_format($due, 2) . " is due on {$document->reference_no}."],
                    ]);
                }

                $documentDate = ($isReceipt ? $document->sale_date : $document->purchase_date)->format('Y-m-d');
                if ($data['payment_date'] < $documentDate) {
                    throw ValidationException::withMessages([
                        'payment_date' => ["The payment date cannot be before the date of {$document->reference_no} ({$documentDate})."],
                    ]);
                }

                $total += $amount;
                $lines[] = ['document' => $document, 'adjusted' => $adjusted, 'amount' => $amount];
            }

            $total = round($total, 2);

            if ($total <= 0) {
                throw ValidationException::withMessages([
                    'allocations' => ['Enter an amount for the opening balance or for at least one invoice.'],
                ]);
            }

            $payment = Payment::create([
                'type' => $type,
                'customer_id' => $isReceipt ? $party->id : null,
                'supplier_id' => $isReceipt ? null : $party->id,
                'party_name' => $party->name,
                'party_phone' => $party->phone,
                'payment_date' => $data['payment_date'],
                'amount' => $total,
                'opening_amount' => $opening,
                'method' => $data['method'],
                'transaction_ref' => $data['transaction_ref'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Numbers run separately for each type: RCT-00001, RCT-00002 ... and PAY-00001, PAY-00002 ...
            $number = Payment::withTrashed()->where('type', $type)->where('id', '<=', $payment->id)->count();
            $payment->update([
                'reference_no' => ($isReceipt ? 'RCT-' : 'PAY-') . str_pad((string) $number, 5, '0', STR_PAD_LEFT),
            ]);

            foreach ($lines as $line) {
                $payment->allocations()->create([
                    'sale_id' => $isReceipt ? $line['document']->id : null,
                    'purchase_id' => $isReceipt ? null : $line['document']->id,
                    'amount' => $line['amount'],
                ]);
                $this->applyToDocument($line['document'], $line['amount'], $line['adjusted']);
            }

            return $payment;
        });

        return response()->json([
            'success' => true,
            'message' => $isReceipt ? 'Receipt created successfully.' : 'Payment created successfully.',
            'data' => $payment->load(['allocations']),
        ], 201);
    }

    public function show(Payment $payment): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Payment fetched successfully.',
            'data' => $payment->load([
                'customer:id,name,phone',
                'supplier:id,name,phone',
                'allocations.sale:id,reference_no,sale_date,total',
                'allocations.purchase:id,reference_no,purchase_date,total',
            ]),
        ]);
    }

    public function cancel(Request $request, Payment $payment): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        DB::transaction(function () use ($payment, $validated) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            abort_if($payment->status === 'cancelled', 409, 'This payment is already cancelled.');

            $isReceipt = $payment->type === 'receipt';

            foreach ($payment->allocations()->get() as $allocation) {
                $document = $isReceipt
                    ? Sale::withTrashed()->lockForUpdate()->findOrFail($allocation->sale_id)
                    : Purchase::withTrashed()->lockForUpdate()->findOrFail($allocation->purchase_id);

                $newPaid = round((float) $document->paid_amount - (float) $allocation->amount, 2);

                // Cash that a return already paid back must still be covered by what was paid
                $cashBack = $isReceipt
                    ? (float) SaleReturn::where('sale_id', $document->id)->where('status', 'completed')->sum('cash_refund')
                    : (float) PurchaseReturn::where('purchase_id', $document->id)->where('status', 'completed')->sum('cash_received');

                if ($newPaid + 0.004 < $cashBack) {
                    throw ValidationException::withMessages([
                        'items' => ["This payment cannot be cancelled because a return on {$document->reference_no} already paid cash back against it. Cancel that return first."],
                    ]);
                }

                $adjusted = $isReceipt
                    ? PartyBalance::saleReturnAdjustments($document->id)
                    : PartyBalance::purchaseReturnAdjustments($document->id);

                $this->applyToDocument($document, -(float) $allocation->amount, $adjusted);
            }

            $payment->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $validated['reason'],
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Payment cancelled successfully.',
            'data' => $payment->refresh(),
        ]);
    }

    /**
     * What a customer still owes: opening balance, unpaid invoices, and summary figures.
     * The receipt form and the customer page use this.
     */
    public function customerOutstanding(Customer $customer): JsonResponse
    {
        $summary = PartyBalance::customerSummary($customer);

        return response()->json([
            'success' => true,
            'message' => 'Outstanding balance fetched successfully.',
            'data' => [
                'party' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'credit_limit' => (float) $customer->credit_limit,
                ],
                'opening_outstanding' => $summary['opening_outstanding'],
                'invoices' => PartyBalance::customerInvoices($customer),
                'total_outstanding' => $summary['outstanding'],
                'summary' => $summary,
            ],
        ]);
    }

    /**
     * What we still owe a supplier: opening balance, unpaid purchases, and summary figures.
     */
    public function supplierOutstanding(Supplier $supplier): JsonResponse
    {
        $summary = PartyBalance::supplierSummary($supplier);

        return response()->json([
            'success' => true,
            'message' => 'Outstanding balance fetched successfully.',
            'data' => [
                'party' => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'phone' => $supplier->phone,
                ],
                'opening_outstanding' => $summary['opening_outstanding'],
                'invoices' => PartyBalance::supplierInvoices($supplier),
                'total_outstanding' => $summary['outstanding'],
                'summary' => $summary,
            ],
        ]);
    }

    /**
     * Add (or, with a negative amount, take back) a payment on a sale or purchase and refresh its payment status.
     */
    private function applyToDocument(Model $document, float $change, float $adjusted): void
    {
        $paid = round((float) $document->paid_amount + $change, 2);
        $due = round((float) $document->total - $paid - $adjusted, 2);

        $document->paid_amount = $paid;
        $document->payment_status = $due <= 0.004 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
        $document->save();
    }
}