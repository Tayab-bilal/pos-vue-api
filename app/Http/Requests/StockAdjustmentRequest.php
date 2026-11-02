<?php

namespace App\Http\Requests;

use App\Models\StockAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'adjustment_date' => ['required', 'date'],
            'type' => ['required', Rule::in(StockAdjustment::TYPES)],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'distinct',
                Rule::exists('products', 'id')->whereNull('deleted_at'),
            ],
            // Send one of these two for each product. The controller checks that one is present.
            'items.*.counted_quantity' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'items.*.quantity_change' => ['nullable', 'numeric', 'between:-9999999999.99,9999999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one product.',
            'items.*.product_id.distinct' => 'The same product is added more than once.',
        ];
    }
}