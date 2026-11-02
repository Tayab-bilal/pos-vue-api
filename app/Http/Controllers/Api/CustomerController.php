<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\PartyBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 10), 100);

        $customers = Customer::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('company_name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->latest()
            ->paginate($perPage);

        // Add the outstanding balance of every customer on this page
        $outstanding = PartyBalance::customerOutstandingMap($customers->getCollection());
        $customers->through(fn ($customer) => array_merge($customer->toArray(), [
            'outstanding' => $outstanding[$customer->id] ?? 0,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Customers fetched successfully.',
            'data' => $customers,
        ]);
    }

    public function store(CustomerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['opening_balance'] = $data['opening_balance'] ?? 0;
        $data['credit_limit'] = $data['credit_limit'] ?? 0;

        $customer = Customer::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully.',
            'data' => $customer,
        ], 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Customer fetched successfully.',
            'data' => array_merge($customer->toArray(), [
                'balance' => PartyBalance::customerSummary($customer),
            ]),
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer): JsonResponse
    {
        $data = $request->validated();
        $data['opening_balance'] = $data['opening_balance'] ?? 0;
        $data['credit_limit'] = $data['credit_limit'] ?? 0;

        $customer->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully.',
            'data' => $customer,
        ]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        // A customer with history stays in the system, so old invoices and balances keep their meaning
        $hasHistory = Sale::where('customer_id', $customer->id)->exists()
            || SaleReturn::where('customer_id', $customer->id)->exists()
            || Payment::where('customer_id', $customer->id)->exists();

        if ($hasHistory) {
            return response()->json([
                'success' => false,
                'message' => 'This customer has sales or payments and cannot be deleted. Mark the customer as inactive instead.',
            ], 409);
        }

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully.',
        ]);
    }
}