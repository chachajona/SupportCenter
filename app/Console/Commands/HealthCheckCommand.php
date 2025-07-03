<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class HealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'health:check
                          {--format=text : Output format (text, json)}
                          {--exit-code : Exit with non-zero code on failures}';

    /**
     * The console command description.
     */
    protected $description = 'Check system health and report status of critical components';

    /**
     * Health check results
     */
    private array $results = [];

    private bool $allHealthy = true;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Running health checks...');

        // Run all health checks
        $this->checkDatabase();
        $this->checkCache();
        $this->checkStorage();
        $this->checkQueue();
        $this->checkApplicationServices();
        $this->checkPerformanceMetrics();

        // Output results
        $this->outputResults();

        // Return appropriate exit code
        if ($this->option('exit-code') && ! $this->allHealthy) {
            return 1;
        }

        return 0;
    }

    /**
     * Check database connectivity and basic operations
     */
    private function checkDatabase(): void
    {
        $this->info('Checking database...');

        try {
            // Test connection
            $startTime = microtime(true);
            DB::connection()->getPdo();
            $connectionTime = (microtime(true) - $startTime) * 1000;

            // Test query performance
            $startTime = microtime(true);
            $userCount = User::count();
            $queryTime = (microtime(true) - $startTime) * 1000;

            // Check if essential tables exist and have data
            $departmentCount = Department::count();
            $ticketCount = Ticket::count();

            $this->results['database'] = [
                'status' => 'healthy',
                'connection_time_ms' => round($connectionTime, 2),
                'query_time_ms' => round($queryTime, 2),
                'users_count' => $userCount,
                'departments_count' => $departmentCount,
                'tickets_count' => $ticketCount,
                'message' => 'Database is operational',
            ];

            // Warn if query times are slow
            if ($connectionTime > 1000 || $queryTime > 500) {
                $this->results['database']['status'] = 'warning';
                $this->results['database']['message'] = 'Database queries are slower than expected';
            }

        } catch (Exception $e) {
            $this->results['database'] = [
                'status' => 'error',
                'message' => 'Database connection failed: '.$e->getMessage(),
            ];
            $this->allHealthy = false;
        }
    }

    /**
     * Check cache system
     */
    private function checkCache(): void
    {
        $this->info('Checking cache...');

        try {
            $testKey = 'health_check_'.time();
            $testValue = 'test_value_'.rand(1000, 9999);

            // Test write
            $startTime = microtime(true);
            Cache::put($testKey, $testValue, 60);
            $writeTime = (microtime(true) - $startTime) * 1000;

            // Test read
            $startTime = microtime(true);
            $retrievedValue = Cache::get($testKey);
            $readTime = (microtime(true) - $startTime) * 1000;

            // Test delete
            Cache::forget($testKey);

            if ($retrievedValue === $testValue) {
                $this->results['cache'] = [
                    'status' => 'healthy',
                    'write_time_ms' => round($writeTime, 2),
                    'read_time_ms' => round($readTime, 2),
                    'driver' => config('cache.default'),
                    'message' => 'Cache is operational',
                ];

                // Warn if operations are slow
                if ($writeTime > 100 || $readTime > 50) {
                    $this->results['cache']['status'] = 'warning';
                    $this->results['cache']['message'] = 'Cache operations are slower than expected';
                }
            } else {
                $this->results['cache'] = [
                    'status' => 'error',
                    'message' => 'Cache read/write test failed',
                ];
                $this->allHealthy = false;
            }

        } catch (Exception $e) {
            $this->results['cache'] = [
                'status' => 'error',
                'message' => 'Cache system error: '.$e->getMessage(),
            ];
            $this->allHealthy = false;
        }
    }

    /**
     * Check storage systems
     */
    private function checkStorage(): void
    {
        $this->info('Checking storage...');

        try {
            $testFile = 'health_check_'.time().'.txt';
            $testContent = 'Health check test content';

            // Test file operations
            $startTime = microtime(true);
            Storage::put($testFile, $testContent);
            $writeTime = (microtime(true) - $startTime) * 1000;

            $startTime = microtime(true);
            $retrievedContent = Storage::get($testFile);
            $readTime = (microtime(true) - $startTime) * 1000;

            // Clean up
            Storage::delete($testFile);

            // Check disk space
            $storagePath = storage_path();
            $freeBytes = disk_free_space($storagePath);
            $totalBytes = disk_total_space($storagePath);
            $usedPercent = round((($totalBytes - $freeBytes) / $totalBytes) * 100, 1);

            $status = 'healthy';
            $message = 'Storage is operational';

            if ($usedPercent > 90) {
                $status = 'error';
                $message = 'Storage space critically low';
                $this->allHealthy = false;
            } elseif ($usedPercent > 80) {
                $status = 'warning';
                $message = 'Storage space running low';
            }

            $this->results['storage'] = [
                'status' => $status,
                'write_time_ms' => round($writeTime, 2),
                'read_time_ms' => round($readTime, 2),
                'disk_usage_percent' => $usedPercent,
                'free_space_gb' => round($freeBytes / 1024 / 1024 / 1024, 2),
                'message' => $message,
            ];

        } catch (Exception $e) {
            $this->results['storage'] = [
                'status' => 'error',
                'message' => 'Storage system error: '.$e->getMessage(),
            ];
            $this->allHealthy = false;
        }
    }

    /**
     * Check queue system
     */
    private function checkQueue(): void
    {
        $this->info('Checking queue...');

        try {
            // Check queue connection
            $connection = Queue::connection();
            $driver = config('queue.default');

            // For database queues, check jobs table
            if ($driver === 'database') {
                $pendingJobs = DB::table('jobs')->count();
                $failedJobs = DB::table('failed_jobs')->count();

                $status = 'healthy';
                $message = 'Queue is operational';

                if ($failedJobs > 10) {
                    $status = 'warning';
                    $message = 'High number of failed jobs detected';
                } elseif ($pendingJobs > 1000) {
                    $status = 'warning';
                    $message = 'Large number of pending jobs in queue';
                }

                $this->results['queue'] = [
                    'status' => $status,
                    'driver' => $driver,
                    'pending_jobs' => $pendingJobs,
                    'failed_jobs' => $failedJobs,
                    'message' => $message,
                ];
            } else {
                // For other drivers, basic connection test
                $this->results['queue'] = [
                    'status' => 'healthy',
                    'driver' => $driver,
                    'message' => 'Queue connection is operational',
                ];
            }

        } catch (Exception $e) {
            $this->results['queue'] = [
                'status' => 'error',
                'message' => 'Queue system error: '.$e->getMessage(),
            ];
            $this->allHealthy = false;
        }
    }

    /**
     * Check application-specific services
     */
    private function checkApplicationServices(): void
    {
        $this->info('Checking application services...');

        try {
            // Check if critical models are accessible
            $checks = [
                'users' => User::count(),
                'departments' => Department::count(),
                'tickets' => Ticket::count(),
            ];

            // Check mail configuration
            $mailConfigured = ! empty(config('mail.mailers.smtp.host')) ||
                ! empty(config('services.resend.key'));

            // Check key application settings
            $appKey = ! empty(config('app.key'));
            $appUrl = config('app.url') !== 'http://localhost';

            $warnings = [];
            if (! $mailConfigured) {
                $warnings[] = 'Mail service not configured';
            }
            if (! $appUrl) {
                $warnings[] = 'APP_URL still set to default localhost';
            }

            $this->results['application'] = [
                'status' => empty($warnings) ? 'healthy' : 'warning',
                'app_key_set' => $appKey,
                'mail_configured' => $mailConfigured,
                'app_url_configured' => $appUrl,
                'model_counts' => $checks,
                'warnings' => $warnings,
                'message' => empty($warnings) ? 'Application services are operational' : 'Some configuration warnings detected',
            ];

        } catch (Exception $e) {
            $this->results['application'] = [
                'status' => 'error',
                'message' => 'Application services error: '.$e->getMessage(),
            ];
            $this->allHealthy = false;
        }
    }

    /**
     * Check performance metrics
     */
    private function checkPerformanceMetrics(): void
    {
        $this->info('Checking performance metrics...');

        try {
            // Memory usage
            $memoryUsage = memory_get_usage(true);
            $memoryPeak = memory_get_peak_usage(true);
            $memoryLimit = $this->parseBytes(ini_get('memory_limit'));

            // PHP version and extensions
            $phpVersion = PHP_VERSION;
            $requiredExtensions = ['pdo', 'mbstring', 'openssl', 'json', 'tokenizer'];
            $missingExtensions = array_filter($requiredExtensions, fn ($ext) => ! extension_loaded($ext));

            // Load average (Unix systems only)
            $loadAverage = null;
            if (function_exists('sys_getloadavg')) {
                $loadAverage = sys_getloadavg();
            }

            $status = 'healthy';
            $warnings = [];

            if (! empty($missingExtensions)) {
                $status = 'error';
                $this->allHealthy = false;
                $warnings[] = 'Missing required PHP extensions: '.implode(', ', $missingExtensions);
            }

            if ($memoryLimit > 0 && ($memoryUsage / $memoryLimit) > 0.8) {
                $status = 'warning';
                $warnings[] = 'High memory usage detected';
            }

            if ($loadAverage && $loadAverage[0] > 2.0) {
                $status = 'warning';
                $warnings[] = 'High system load detected';
            }

            $this->results['performance'] = [
                'status' => $status,
                'php_version' => $phpVersion,
                'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
                'memory_peak_mb' => round($memoryPeak / 1024 / 1024, 2),
                'memory_limit_mb' => $memoryLimit > 0 ? round($memoryLimit / 1024 / 1024, 2) : 'unlimited',
                'load_average' => $loadAverage,
                'missing_extensions' => $missingExtensions,
                'warnings' => $warnings,
                'message' => empty($warnings) ? 'Performance metrics are normal' : 'Performance issues detected',
            ];

        } catch (Exception $e) {
            $this->results['performance'] = [
                'status' => 'error',
                'message' => 'Performance check error: '.$e->getMessage(),
            ];
            $this->allHealthy = false;
        }
    }

    /**
     * Output results based on format
     */
    private function outputResults(): void
    {
        if ($this->option('format') === 'json') {
            $this->line(json_encode([
                'timestamp' => now()->toISOString(),
                'overall_status' => $this->allHealthy ? 'healthy' : 'unhealthy',
                'checks' => $this->results,
            ], JSON_PRETTY_PRINT));

            return;
        }

        // Text format output
        $this->newLine();
        $this->info('=== Health Check Results ===');
        $this->info('Timestamp: '.now()->format('Y-m-d H:i:s'));
        $this->newLine();

        foreach ($this->results as $component => $result) {
            $status = $result['status'];
            $icon = match ($status) {
                'healthy' => '✅',
                'warning' => '⚠️',
                'error' => '❌',
                default => '❓'
            };

            $this->line(sprintf('%s %s: %s', $icon, strtoupper($component), $result['message']));

            if ($status !== 'healthy' && isset($result['warnings'])) {
                foreach ($result['warnings'] as $warning) {
                    $this->warn("  - $warning");
                }
            }
        }

        $this->newLine();
        $overallStatus = $this->allHealthy ? 'HEALTHY' : 'UNHEALTHY';
        $statusColor = $this->allHealthy ? 'info' : 'error';
        $this->$statusColor("Overall System Status: $overallStatus");
    }

    /**
     * Parse memory limit string to bytes
     */
    private function parseBytes(string $size): int
    {
        $size = trim($size);
        $last = strtolower($size[strlen($size) - 1]);
        $value = (int) $size;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }
}
