<?php

namespace App\Http\Requests;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // On edit, the route has the expense being edited
        $expense = $this->route('expense');

        // A category must be active, except the one already on this expense
        $categoryRule = Rule::exists('expense_categories', 'id')->whereNull('deleted_at');
        if (! $expense || (int) $this->input('expense_category_id') !== (int) $expense->expense_category_id) {
            $categoryRule->where('is_active', true);
        }

        return [
            'expense_category_id' => ['required', $categoryRule],
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'transaction_ref' => ['nullable', 'string', 'max:100'],
            'paid_to' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}