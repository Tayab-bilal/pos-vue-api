<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // On edit, the route has the sale being edited
        $sale = $this->route('sale');

        // A saved customer must be active, except the customer already on this sale
        $customerRule = Rule::exists('customers', 'id')->whereNull('deleted_at');
        if (! $sale || (int) $this->input('customer_id') !== (int) $sale->customer_id) {
            $customerRule->where('is_active', true);
        }

        // Products must be active, except products already on this sale
        $existingProductIds = $sale ? $sale->items()->pluck('product_id')->all() : [];
        $productRule = Rule::exists('products', 'id')
            ->whereNull('deleted_at')
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $existingProductIds));

        return [
            'customer_id' => ['nullable', $customerRule],
            'customer_name' => ['nullable', 'required_if:save_customer,true', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'save_customer' => ['sometimes', 'boolean'],
            'sale_date' => ['required', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'distinct', $productRule],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_name.required_if' => 'Enter a name to save this customer.',
            'items.required' => 'Add at least one product.',
            'items.*.product_id.distinct' => 'The same product is added more than once.',
            'items.*.product_id.exists' => 'The selected product is not available for sale.',
        ];
    }
}