<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_id' => ['required', Rule::exists('purchases', 'id')->whereNull('deleted_at')],
            'return_date' => ['required', 'date'],
            'refund_method' => ['required', Rule::in(['cash', 'adjust_due'])],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'distinct', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Select at least one product to return.',
            'items.*.product_id.distinct' => 'The same product is added more than once.',
        ];
    }
}