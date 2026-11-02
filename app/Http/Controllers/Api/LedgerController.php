<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Supplier;
use App\Services\LedgerService;
use App\Services\PartyBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class LedgerController extends Controller
{
    /**
     * Every customer with what they owe us (receivables), biggest first.
     */
    public function customers(Request $request): JsonResponse
    {
        $customers = Customer::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('company_name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->get();

        $outstanding = PartyBalance::customerOutstandingMap($customers);

        $rows = $customers->map(fn ($customer) => [
            'id' => $customer->id,
            'name' => $customer->name,
            'company_name' => $customer->company_name,
            'phone' => $customer->phone,
            'is_active' => $customer->is_active,
            'opening_balance' => (float) $customer->opening_balance,
            'credit_limit' => (float) $customer->credit_limit,
            'outstanding' => $outstanding[$customer->id] ?? 0,
        ]);

        return $this->listResponse($request, $rows, 'Customer ledgers fetched successfully.');
    }

    /**
     * Every supplier with what we owe them (payables), biggest first.
     */
    public function suppliers(Request $request): JsonResponse
    {
        $suppliers = Supplier::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('company_name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->get();

        $outstanding = PartyBalance::supplierOutstandingMap($suppliers);

        $rows = $suppliers->map(fn ($supplier) => [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'company_name' => $supplier->company_name,
            'phone' => $supplier->phone,
            'is_active' => $supplier->is_active,
            'opening_balance' => (float) $supplier->opening_balance,
            'credit_limit' => null,
            'outstanding' => $outstanding[$supplier->id] ?? 0,
        ]);

        return $this->listResponse($request, $rows, 'Supplier ledgers fetched successfully.');
    }

    public function customerStatement(Request $request, Customer $customer): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        return response()->json([
            'success' => true,
            'message' => 'Customer ledger fetched successfully.',
            'data' => LedgerService::customer($customer, $from, $to),
        ]);
    }

    public function supplierStatement(Request $request, Supplier $supplier): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        return response()->json([
            'success' => true,
            'message' => 'Supplier ledger fetched successfully.',
            'data' => LedgerService::supplier($supplier, $from, $to),
        ]);
    }

    private function dateRange(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [$validated['from'] ?? null, $validated['to'] ?? null];
    }

    /**
     * The outstanding balance is worked out per party, so filtering, sorting and paging happen here.
     */
    private function listResponse(Request $request, Collection $rows, string $message): JsonResponse
    {
        if ($request->boolean('only_due')) {
            $rows = $rows->filter(fn ($row) => $row['outstanding'] > 0.004);
        }

        $rows = $rows->sortByDesc('outstanding')->values();

        // Totals cover every row that matches the filters, not just this page
        $summary = [
            'parties' => $rows->count(),
            'with_balance' => $rows->filter(fn ($row) => $row['outstanding'] > 0.004)->count(),
            'total_outstanding' => round($rows->sum('outstanding'), 2),
        ];

        $perPage = min((int) $request->input('per_page', 10), 100);
        $page = LengthAwarePaginator::resolveCurrentPage();

        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url()],
        );

        return response()->json([
            'success' => true,
            'message' => $message,
            'summary' => $summary,
            'data' => $paginator,
        ]);
    }
}