<?php

declare(strict_types=1);

namespace App\Services\Setup;

use App\Models\SetupStatus;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class SetupResetService
{
    /**
     * Fully reset the setup wizard state.
     *
     * @param  int|null  $performedBy  Authenticated user ID (if any)
     * @param  string  $reason  Reason for the reset (for audit/logging)
     * @param  array<string, mixed>  $context  Additional context to log/audit
     */
    public function reset(?int $performedBy = null, string $reason = 'setup_reset', array $context = []): void
    {
        // Remove lock file if present
        $lockFile = storage_path('app/setup.lock');
        if (File::exists($lockFile)) {
            File::delete($lockFile);
        }

        // 1. Refresh the database structure to guarantee a clean slate.
        //    This drops ALL tables and re-runs every migration so that required
        //    tables (including `setup_status`) exist before we attempt to
        //    manipulate them. This is safe here because the wizard only
        //    operates before the system is live.
        Artisan::call('migrate:fresh', ['--force' => true]);

        // 2. Reset progress in the setup_status table (now recreated). If for
        //    some unexpected reason the migration set does not include the
        //    table, swallow the error so the reset can still proceed.
        try {
            SetupStatus::resetSetup();
        } catch (\Throwable $e) {
            Log::warning('setup_status table not available during reset', ['error' => $e->getMessage()]);
        }

        // Clear caches so that fresh config/routes will be loaded next request
        Artisan::call('optimize:clear');

        // Log critical action (uses Laravel logger)
        Log::critical('Setup system reset', array_merge([
            'performed_by' => $performedBy,
            'reason' => $reason,
        ], $context));

        // Optionally record an audit entry if the PermissionAudit model exists
        if ($performedBy !== null && class_exists('App\\Models\\PermissionAudit')) {
            /** @var class-string \App\Models\PermissionAudit $auditClass */
            $auditClass = 'App\\Models\\PermissionAudit';
            $auditClass::create([
                'user_id' => $performedBy,
                'action' => 'setup_reset',
                'new_values' => [
                    'reason' => $reason,
                    'reset_at' => now(),
                ],
                'performed_by' => $performedBy,
                ...$context,
            ]);
        }
    }
}
