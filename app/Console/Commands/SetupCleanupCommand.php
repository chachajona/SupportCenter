<?php

namespace App\Console\Commands;

use App\Models\SetupStatus;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SetupCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'setup:cleanup {--force : Force cleanup without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up setup system after successful installation';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('force')) {
            if (! $this->confirm('This will permanently disable the setup system. Continue?')) {
                $this->info('Setup cleanup cancelled.');

                return 1;
            }
        }

        try {
            // Check if setup is actually completed
            $setupCompleted = SetupStatus::where('step', 'setup_completed')
                ->where('completed', true)
                ->exists();

            if (! $setupCompleted) {
                $this->error('Setup is not completed. Cannot cleanup.');

                return 1;
            }

            // Create setup lock file
            $setupLockFile = storage_path('app/setup.lock');
            file_put_contents($setupLockFile, json_encode([
                'completed_at' => now()->toISOString(),
                'completed_by' => 'artisan_command',
                'version' => config('app.version', '1.0.0'),
                'cleanup_method' => 'artisan_command',
            ]));

            // Clear setup-related cache
            Cache::forget('setup_status');
            Cache::forget('setup_progress');

            // In production, you might want to remove setup files entirely
            if (app()->environment('production')) {
                $this->cleanupSetupFiles();
            }

            $this->info('Setup system cleaned up successfully.');
            $this->info('Setup lock file created at: '.$setupLockFile);

            return 0;
        } catch (Exception $e) {
            $this->error('Failed to cleanup setup system: '.$e->getMessage());

            return 1;
        }
    }

    /**
     * Clean up setup files in production
     */
    private function cleanupSetupFiles(): void
    {
        // List of setup-related files that can be removed in production
        $setupFiles = [
            resource_path('js/pages/setup'),
        ];

        foreach ($setupFiles as $file) {
            if (file_exists($file)) {
                if (is_dir($file)) {
                    $this->info("Consider removing setup directory: {$file}");
                } else {
                    $this->info("Consider removing setup file: {$file}");
                }
            }
        }

        $this->warn('Note: Setup files not automatically removed. Review and remove manually if desired.');
    }
}
