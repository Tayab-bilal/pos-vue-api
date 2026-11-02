<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    private const RELATIONS = ['category:id,name', 'unit:id,name,short_name'];

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 10), 100);

        $products = Product::query()
            ->with(self::RELATIONS)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%")
                      ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->boolean('low_stock'), fn ($q) => $q->whereColumn('stock_quantity', '<=', 'alert_quantity'))
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Products fetched successfully.',
            'data' => $products,
        ]);
    }

       public function store(ProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['stock_quantity'] = $data['stock_quantity'] ?? 0;
        $data['alert_quantity'] = $data['alert_quantity'] ?? 0;

        $product = DB::transaction(function () use ($data) {
            $product = Product::create($data);

            // Auto-generate an SKU when none was provided, e.g. PRD-00001
            if (empty($product->sku)) {
                $product->update(['sku' => 'PRD-' . str_pad((string) $product->id, 5, '0', STR_PAD_LEFT)]);
            }

            // Opening stock is the first entry in the product's stock history
            if ((float) $product->stock_quantity > 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'opening',
                    'quantity' => $product->stock_quantity,
                    'balance_after' => $product->stock_quantity,
                    'reference_type' => 'product',
                    'reference_id' => $product->id,
                    'reference_no' => $product->sku,
                    'note' => 'Opening stock',
                ]);
            }

            return $product;
        });

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'data' => $product->load(self::RELATIONS),
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Product fetched successfully.',
            'data' => $product->load(self::RELATIONS),
        ]);
    }

    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();
        $data['alert_quantity'] = $data['alert_quantity'] ?? 0;

        $product->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'data' => $product->load(self::RELATIONS),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        // Soft delete keeps the product visible in old invoices and reports.
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ]);
    }
}