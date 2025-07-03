<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitoring
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $startPeakMemory = memory_get_peak_usage(true);

        $response = $next($request);

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $endPeakMemory = memory_get_peak_usage(true);

        $executionTime = ($endTime - $startTime) * 1000; // milliseconds
        $memoryUsage = ($endMemory - $startMemory) / 1024 / 1024; // MB
        $peakMemoryIncrease = ($endPeakMemory - $startPeakMemory) / 1024 / 1024; // MB

        $performanceData = [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'route_name' => $request->route()?->getName(),
            'execution_time_ms' => round($executionTime, 2),
            'memory_usage_mb' => round($memoryUsage, 2),
            'peak_memory_increase_mb' => round($peakMemoryIncrease, 2),
            'response_status' => $response->getStatusCode(),
            'user_id' => Auth::id(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
        ];

        // Log slow requests (>500ms)
        if ($executionTime > 500) {
            Log::channel('performance')->warning('Slow request detected', array_merge($performanceData, [
                'alert_type' => 'slow_request',
                'threshold_ms' => 500,
            ]));
        }

        // Log high memory usage (>10MB increase)
        if ($memoryUsage > 10) {
            Log::channel('performance')->warning('High memory usage detected', array_merge($performanceData, [
                'alert_type' => 'high_memory_usage',
                'threshold_mb' => 10,
            ]));
        }

        // Log all API requests for monitoring
        if ($request->is('api/*')) {
            Log::channel('api_performance')->info('API request metrics', $performanceData);
        }

        // Add performance headers for debugging (only in non-production)
        if (!app()->isProduction()) {
            $response->headers->set('X-Execution-Time', $executionTime . 'ms');
            $response->headers->set('X-Memory-Usage', $memoryUsage . 'MB');
            $response->headers->set('X-Peak-Memory', $peakMemoryIncrease . 'MB');
        }

        // Store metrics in cache for dashboard aggregation
        $this->storeMetricsForDashboard($performanceData);

        return $response;
    }

    /**
     * Store performance metrics in cache for dashboard aggregation
     */
    private function storeMetricsForDashboard(array $performanceData): void
    {
        $cacheKey = 'performance_metrics_' . now()->format('Y_m_d_H');

        // Get existing metrics for this hour
        $existingMetrics = cache()->get($cacheKey, []);

        // Add current request metrics
        $existingMetrics[] = [
            'execution_time' => $performanceData['execution_time_ms'],
            'memory_usage' => $performanceData['memory_usage_mb'],
            'route' => $performanceData['route_name'] ?? 'unknown',
            'method' => $performanceData['method'],
            'status' => $performanceData['response_status'],
            'timestamp' => $performanceData['timestamp'],
        ];

        // Keep only last 1000 requests per hour to prevent memory issues
        if (count($existingMetrics) > 1000) {
            $existingMetrics = array_slice($existingMetrics, -1000);
        }

        // Store for 1 hour + 5 minutes buffer
        cache()->put($cacheKey, $existingMetrics, now()->addMinutes(65));
    }

    /**
     * Get aggregated performance metrics for the current hour
     */
    public static function getHourlyMetrics(): array
    {
        $cacheKey = 'performance_metrics_' . now()->format('Y_m_d_H');
        $metrics = cache()->get($cacheKey, []);

        if (empty($metrics)) {
            return [
                'avg_response_time' => 0,
                'max_response_time' => 0,
                'total_requests' => 0,
                'slow_requests' => 0,
                'avg_memory_usage' => 0,
                'error_rate' => 0,
            ];
        }

        $executionTimes = array_column($metrics, 'execution_time');
        $memoryUsages = array_column($metrics, 'memory_usage');
        $statuses = array_column($metrics, 'status');

        $totalRequests = count($metrics);
        $slowRequests = count(array_filter($executionTimes, fn($time) => $time > 500));
        $errorRequests = count(array_filter($statuses, fn($status) => $status >= 400));

        return [
            'avg_response_time' => round(array_sum($executionTimes) / $totalRequests, 2),
            'max_response_time' => round(max($executionTimes), 2),
            'total_requests' => $totalRequests,
            'slow_requests' => $slowRequests,
            'avg_memory_usage' => round(array_sum($memoryUsages) / $totalRequests, 2),
            'error_rate' => round(($errorRequests / $totalRequests) * 100, 2),
        ];
    }
}
