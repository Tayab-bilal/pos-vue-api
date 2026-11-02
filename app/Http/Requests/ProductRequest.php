<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        $rules = [
            'category_id' => ['required', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'unit_id' => ['required', Rule::exists('units', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($productId)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('products', 'barcode')->ignore($productId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'sale_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'alert_quantity' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        // Opening stock can only be set when creating. Later changes go through
        // purchases, sales, returns and stock adjustments.
        if ($this->isMethod('post')) {
            $rules['stock_quantity'] = ['nullable', 'numeric', 'min:0', 'max:9999999999.99'];
        }

        return $rules;
    }
}