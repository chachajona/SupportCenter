<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\PerformanceMonitoring;
use Illuminate\Http\JsonResponse;

final class SystemHealthController extends Controller
{
    /**
     * Basic health check endpoint.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Performance metrics for the current hour.
     */
    public function performance(): JsonResponse
    {
        return response()->json(PerformanceMonitoring::getHourlyMetrics());
    }

    /**
     * Additional system metrics placeholder.
     */
    public function metrics(): JsonResponse
    {
        // In the future, collect CPU, memory, disk, etc.
        return response()->json([
            'uptime' => app()->uptime() ?? null,
        ]);
    }
}
