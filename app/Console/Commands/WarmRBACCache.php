<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PermissionCacheService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class WarmRBACCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rbac:warm-cache {--users=* : Specific user IDs to warm cache for} {--batch-size=100 : Number of users to process at once}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm RBAC cache by preloading permissions and roles for users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔥 Warming RBAC Cache...');

        $cacheService = app(PermissionCacheService::class);
        $specificUsers = $this->option('users');
        $batchSize = (int) $this->option('batch-size');

        if (!empty($specificUsers)) {
            // Warm cache for specific users
            $this->warmSpecificUsers($cacheService, $specificUsers);
        } else {
            // Warm cache for all users
            $this->warmAllUsers($cacheService, $batchSize);
        }

        $this->newLine();
        $this->info('✅ RBAC cache warming completed!');

        return Command::SUCCESS;
    }

    /**
     * Warm cache for specific users.
     */
    private function warmSpecificUsers(PermissionCacheService $cacheService, array $userIds): void
    {
        $this->info("Warming cache for " . count($userIds) . " specific user(s)...");

        $bar = $this->output->createProgressBar(count($userIds));
        $bar->start();

        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if ($user) {
                $cacheService->warmUserCache($userId);
                $this->line("  ✓ Warmed cache for user: {$user->name} (ID: {$userId})");
            } else {
                $this->warn("  ⚠ User not found: {$userId}");
            }
            $bar->advance();
        }

        $bar->finish();
    }

    /**
     * Warm cache for all users in batches.
     */
    private function warmAllUsers(PermissionCacheService $cacheService, int $batchSize): void
    {
        $totalUsers = User::count();
        $this->info("Warming cache for {$totalUsers} users in batches of {$batchSize}...");

        $bar = $this->output->createProgressBar($totalUsers);
        $bar->start();

        $processedCount = 0;
        $errorCount = 0;

        User::chunk($batchSize, function ($users) use ($cacheService, $bar, &$processedCount, &$errorCount) {
            foreach ($users as $user) {
                try {
                    $cacheService->warmUserCache($user->id);
                    $processedCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->warn("  ⚠ Failed to warm cache for user {$user->id}: " . $e->getMessage());
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("Cache warming summary:");
        $this->line("  ✓ Successfully processed: {$processedCount} users");
        if ($errorCount > 0) {
            $this->warn("  ⚠ Errors encountered: {$errorCount} users");
        }

        // Display cache statistics
        $this->displayCacheStats($cacheService);
    }

    /**
     * Display cache statistics.
     */
    private function displayCacheStats(PermissionCacheService $cacheService): void
    {
        $this->newLine();
        $this->info('📊 Cache Statistics:');

        $stats = $cacheService->getCacheStats();

        $this->table(
            ['Setting', 'Value'],
            [
                ['Cache Store', $stats['store']],
                ['Cache Prefix', $stats['prefix']],
                ['TTL (seconds)', $stats['ttl']],
                ['Tags Supported', $stats['tags_supported'] ? 'Yes' : 'No'],
            ]
        );

        // Test cache performance
        $this->info('🚀 Testing cache performance...');

        $sampleUser = User::first();
        if ($sampleUser) {
            $iterations = 10;
            $totalTime = 0;

            for ($i = 0; $i < $iterations; $i++) {
                $startTime = microtime(true);
                $cacheService->getUserPermissions($sampleUser->id);
                $endTime = microtime(true);
                $totalTime += ($endTime - $startTime);
            }

            $averageTime = ($totalTime / $iterations) * 1000; // Convert to milliseconds
            $this->line("Average cache response time: " . number_format($averageTime, 2) . "ms (over {$iterations} requests)");

            if ($averageTime < 10) {
                $this->info("✅ Excellent cache performance!");
            } elseif ($averageTime < 50) {
                $this->line("✅ Good cache performance");
            } else {
                $this->warn("⚠️ Cache performance could be improved");
            }
        }
    }
}
