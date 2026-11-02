<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\LedgerController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\PurchaseReturnController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SaleReturnController;
use App\Http\Controllers\Api\StockAdjustmentController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Everything below needs a signed-in user. Without one the server answers 401.
Route::middleware(['auth:sanctum'])->group(function () {
    // Only what the screen needs. Never the whole user record.
    Route::get('/user', fn (Request $request) => $request->user()->only(['id', 'name', 'email']));

    Route::get('dashboard', [DashboardController::class, 'index']);

    Route::get('search', [SearchController::class, 'index'])->middleware('throttle:120,1');

    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('units', UnitController::class);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('customers', CustomerController::class);

    Route::apiResource('purchases', PurchaseController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel']);
    Route::get('purchases/{purchase}/returnable', [PurchaseReturnController::class, 'returnable']);
    Route::apiResource('purchase-returns', PurchaseReturnController::class)->only(['index', 'store', 'show']);
    Route::post('purchase-returns/{purchase_return}/cancel', [PurchaseReturnController::class, 'cancel']);

    Route::apiResource('sales', SaleController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('sales/{sale}/cancel', [SaleController::class, 'cancel']);
    Route::get('sales/{sale}/returnable', [SaleReturnController::class, 'returnable']);
    Route::apiResource('sale-returns', SaleReturnController::class)->only(['index', 'store', 'show']);
    Route::post('sale-returns/{sale_return}/cancel', [SaleReturnController::class, 'cancel']);

    Route::apiResource('stock-adjustments', StockAdjustmentController::class)->only(['index', 'store', 'show']);
    Route::post('stock-adjustments/{stock_adjustment}/cancel', [StockAdjustmentController::class, 'cancel']);
    Route::get('stock', [StockController::class, 'index']);
    Route::get('stock-movements', [StockController::class, 'movements']);

    Route::controller(PaymentController::class)->group(function () {
        Route::get('receipts', 'index')->defaults('type', 'receipt');
        Route::post('receipts', 'store')->defaults('type', 'receipt');
        Route::get('supplier-payments', 'index')->defaults('type', 'payment');
        Route::post('supplier-payments', 'store')->defaults('type', 'payment');
        Route::get('payments/{payment}', 'show');
        Route::post('payments/{payment}/cancel', 'cancel');
        Route::get('customers/{customer}/outstanding', 'customerOutstanding');
        Route::get('suppliers/{supplier}/outstanding', 'supplierOutstanding');
    });

    Route::controller(LedgerController::class)->prefix('ledgers')->group(function () {
        Route::get('customers', 'customers');
        Route::get('suppliers', 'suppliers');
        Route::get('customers/{customer}', 'customerStatement');
        Route::get('suppliers/{supplier}', 'supplierStatement');
    });

    Route::controller(ReportController::class)->prefix('reports')->group(function () {
        Route::get('sales', 'sales');
        Route::get('purchases', 'purchases');
        Route::get('profit-loss', 'profitLoss');
        Route::get('expenses', 'expenses');
        Route::get('stock-valuation', 'stockValuation');
    });

    Route::apiResource('expense-categories', ExpenseCategoryController::class);
    Route::apiResource('expenses', ExpenseController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('expenses/{expense}/cancel', [ExpenseController::class, 'cancel']);
});