<?php

namespace App\Http\Requests;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // The route tells us whether this is a receipt (customer) or a payment (supplier)
        $isReceipt = $this->route('type') === 'receipt';
        $partyField = $isReceipt ? 'customer_id' : 'supplier_id';
        $partyTable = $isReceipt ? 'customers' : 'suppliers';

        return [
            $partyField => ['required', Rule::exists($partyTable, 'id')->whereNull('deleted_at')],
            'payment_date' => ['required', 'date'],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'transaction_ref' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],

            // Part of the payment that settles the opening balance
            'opening_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],

            // Parts that settle specific invoices (sales for a receipt, purchases for a payment)
            'allocations' => ['nullable', 'array'],
            'allocations.*.document_id' => ['required', 'integer', 'distinct'],
            'allocations.*.amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'allocations.*.document_id.distinct' => 'The same invoice is added more than once.',
        ];
    }
}