<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'today' => ['nullable', 'date'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dashboard fetched successfully.',
            'data' => DashboardService::build($validated['today'] ?? null),
        ]);
    }
}