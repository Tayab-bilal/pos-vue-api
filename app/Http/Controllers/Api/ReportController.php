<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function sales(Request $request): JsonResponse
    {
        return $this->report($request, 'Sales report fetched successfully.', fn ($from, $to) => ReportService::sales($from, $to));
    }

    public function purchases(Request $request): JsonResponse
    {
        return $this->report($request, 'Purchases report fetched successfully.', fn ($from, $to) => ReportService::purchases($from, $to));
    }

    public function profitLoss(Request $request): JsonResponse
    {
        return $this->report($request, 'Profit and loss report fetched successfully.', fn ($from, $to) => ReportService::profitLoss($from, $to));
    }

    public function expenses(Request $request): JsonResponse
    {
        return $this->report($request, 'Expenses report fetched successfully.', fn ($from, $to) => ReportService::expenses($from, $to));
    }

    public function stockValuation(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Stock valuation fetched successfully.',
            'data' => ReportService::stockValuation(),
        ]);
    }

    private function report(Request $request, string $message, callable $build): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $build($validated['from'] ?? null, $validated['to'] ?? null),
        ]);
    }
}