<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 10), 100);

        $query = $this->filtered($request);

        // The total covers every expense that matches the filters (not just this page), cancelled ones excluded
        $summary = (clone $query)
            ->where('status', 'completed')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
            ->first();

        $expenses = $query
            ->with('category:id,name')
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Expenses fetched successfully.',
            'summary' => $summary,
            'data' => $expenses,
        ]);
    }

    public function store(ExpenseRequest $request): JsonResponse
    {
        $expense = DB::transaction(function () use ($request) {
            $expense = Expense::create($request->validated());
            $expense->update(['reference_no' => 'EXP-' . str_pad((string) $expense->id, 5, '0', STR_PAD_LEFT)]);

            return $expense;
        });

        return response()->json([
            'success' => true,
            'message' => 'Expense created successfully.',
            'data' => $expense->load('category:id,name'),
        ], 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Expense fetched successfully.',
            'data' => $expense->load('category:id,name'),
        ]);
    }

    public function update(ExpenseRequest $request, Expense $expense): JsonResponse
    {
        DB::transaction(function () use ($request, $expense) {
            $locked = Expense::lockForUpdate()->findOrFail($expense->id);
            abort_if($locked->status === 'cancelled', 409, 'A cancelled expense cannot be edited.');

            $locked->update($request->validated());
        });

        return response()->json([
            'success' => true,
            'message' => 'Expense updated successfully.',
            'data' => $expense->refresh()->load('category:id,name'),
        ]);
    }

    public function cancel(Request $request, Expense $expense): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        DB::transaction(function () use ($expense, $validated) {
            $locked = Expense::lockForUpdate()->findOrFail($expense->id);
            abort_if($locked->status === 'cancelled', 409, 'This expense is already cancelled.');

            $locked->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $validated['reason'],
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Expense cancelled successfully.',
            'data' => $expense->refresh(),
        ]);
    }

    private function filtered(Request $request): Builder
    {
        return Expense::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('reference_no', 'like', "%{$search}%")
                      ->orWhere('paid_to', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('transaction_ref', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('expense_category_id'), fn ($q) => $q->where('expense_category_id', $request->integer('expense_category_id')))
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->input('method')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('expense_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('expense_date', '<=', $request->input('date_to')));
    }
}