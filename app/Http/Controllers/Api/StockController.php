<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    /**
     * Stock overview: every product with its current stock, plus totals for the summary cards.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 10), 100);

        $products = Product::query()
            ->with(['category:id,name', 'unit:id,short_name'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%")
                      ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('status'), function ($query) use ($request) {
                match ($request->input('status')) {
                    'out' => $query->where('stock_quantity', '<=', 0),
                    'low' => $query->where('stock_quantity', '>', 0)->whereColumn('stock_quantity', '<=', 'alert_quantity'),
                    'in' => $query->where('stock_quantity', '>', 0)->whereColumn('stock_quantity', '>', 'alert_quantity'),
                    default => null,
                };
            })
            ->orderBy('name')
            ->paginate($perPage);

        // Totals for all products, regardless of the filters above
        $summary = Product::query()
            ->selectRaw('
                COUNT(*) as total_products,
                COALESCE(SUM(stock_quantity * cost_price), 0) as stock_value,
                COALESCE(SUM(stock_quantity * sale_price), 0) as retail_value,
                COALESCE(SUM(CASE WHEN stock_quantity <= 0 THEN 1 ELSE 0 END), 0) as out_of_stock,
                COALESCE(SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= alert_quantity THEN 1 ELSE 0 END), 0) as low_stock
            ')
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Stock fetched successfully.',
            'summary' => $summary,
            'data' => $products,
        ]);
    }

    /**
     * Stock movements: the history of every stock change, newest first.
     */
    public function movements(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 15), 100);

        $movements = StockMovement::query()
            ->with('product:id,name,sku')
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('reference_no', 'like', "%{$search}%")
                      ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('date_to')))
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Stock movements fetched successfully.',
            'data' => $movements,
        ]);
    }
}