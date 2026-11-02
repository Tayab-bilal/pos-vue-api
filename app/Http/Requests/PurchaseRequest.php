<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // On edit, the route has the purchase being edited
        $purchase = $this->route('purchase');

        // A saved supplier must be active, except the supplier already on this purchase
        $supplierRule = Rule::exists('suppliers', 'id')->whereNull('deleted_at');
        if (! $purchase || (int) $this->input('supplier_id') !== (int) $purchase->supplier_id) {
            $supplierRule->where('is_active', true);
        }

        return [
            'supplier_id' => ['nullable', $supplierRule],
            'supplier_name' => ['required_without:supplier_id', 'nullable', 'string', 'max:255'],
            'supplier_phone' => ['nullable', 'string', 'max:30'],
            'purchase_date' => ['required', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'distinct',
                Rule::exists('products', 'id')->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_name.required_without' => 'Select a supplier or enter a one-time supplier name.',
            'items.required' => 'Add at least one product.',
            'items.*.product_id.distinct' => 'The same product is added more than once.',
        ];
    }
}