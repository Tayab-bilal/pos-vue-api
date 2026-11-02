<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierRequest;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Services\PartyBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 10), 100);

        $suppliers = Supplier::query()
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

        // Add the outstanding balance (what we owe) of every supplier on this page
        $outstanding = PartyBalance::supplierOutstandingMap($suppliers->getCollection());
        $suppliers->through(fn ($supplier) => array_merge($supplier->toArray(), [
            'outstanding' => $outstanding[$supplier->id] ?? 0,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Suppliers fetched successfully.',
            'data' => $suppliers,
        ]);
    }

    public function store(SupplierRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['opening_balance'] = $data['opening_balance'] ?? 0;

        $supplier = Supplier::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Supplier created successfully.',
            'data' => $supplier,
        ], 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Supplier fetched successfully.',
            'data' => array_merge($supplier->toArray(), [
                'balance' => PartyBalance::supplierSummary($supplier),
            ]),
        ]);
    }

    public function update(SupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $data = $request->validated();
        $data['opening_balance'] = $data['opening_balance'] ?? 0;

        $supplier->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Supplier updated successfully.',
            'data' => $supplier,
        ]);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        // A supplier with history stays in the system, so old purchases and balances keep their meaning
        $hasHistory = Purchase::where('supplier_id', $supplier->id)->exists()
            || PurchaseReturn::where('supplier_id', $supplier->id)->exists()
            || Payment::where('supplier_id', $supplier->id)->exists();

        if ($hasHistory) {
            return response()->json([
                'success' => false,
                'message' => 'This supplier has purchases or payments and cannot be deleted. Mark the supplier as inactive instead.',
            ], 409);
        }

        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Supplier deleted successfully.',
        ]);
    }
}