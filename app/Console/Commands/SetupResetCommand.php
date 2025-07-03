<?php

namespace App\Console\Commands;

use App\Services\Setup\SetupResetService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SetupResetCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'setup:reset {--force : Do not ask for confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Completely reset the Support Center installation wizard and database.';

    public function __construct(private readonly SetupResetService $resetService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('force')) {
            if (! $this->confirm('This will DROP ALL DATABASE TABLES and restart the setup wizard. Continue?')) {
                $this->info('Setup reset cancelled.');

                return self::SUCCESS;
            }
        }

        try {
            // Verify we can connect before proceeding to give a clearer error if connection fails.
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $this->error('Database connection failed: '.$e->getMessage());
            $this->error('Ensure your .env has valid DB_* credentials before running the reset.');

            return self::FAILURE;
        }

        $this->components->task('Resetting setup system', function () {
            $this->resetService->reset(null, 'artisan_reset_command');
        });

        $this->info('✅ Setup wizard has been reset. You can now visit /setup to start again.');

        return self::SUCCESS;
    }
}
