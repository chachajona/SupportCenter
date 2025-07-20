<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateAdminRequest;
use App\Models\SetupStatus;
use App\Models\User;
use App\Services\Setup\EnvironmentCheckService;
use App\Services\Setup\EnvManager;
use App\Services\Setup\SetupResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class SetupController extends Controller
{
    protected $envManager;
    protected $environmentCheckService;
    protected $setupResetService;

    public function __construct(EnvManager $envManager, EnvironmentCheckService $environmentCheckService, SetupResetService $setupResetService)
    {
        $this->envManager = $envManager;
        $this->environmentCheckService = $environmentCheckService;
        $this->setupResetService = $setupResetService;
    }

    public function index()
    {
        $step = $this->getCurrentStep();
        return Redirect::route("setup.{$step}");
    }

    public function showPrerequisites()
    {
        $results = $this->environmentCheckService->run();
        SetupStatus::markCompleted('prerequisites_checked', ['errors' => $results['errors']]);

        return Inertia::render('setup/index', [
            'currentStep' => 'prerequisites',
            'steps' => $this->getStepsStatus(),
            'data' => $results,
        ]);
    }

    public function showDatabase()
    {
        // Validate that prerequisites are met before showing this step
        if (!SetupStatus::isCompleted('prerequisites_checked')) {
            return redirect()->route('setup.index');
        }

        $connection = config('database.default');
        return Inertia::render('setup/index', [
            'currentStep' => 'database',
            'steps' => $this->getStepsStatus(),
            'data' => [
                'db_host' => config("database.connections.{$connection}.host"),
                'db_port' => config("database.connections.{$connection}.port"),
                'db_database' => config("database.connections.{$connection}.database"),
                'db_username' => config("database.connections.{$connection}.username"),
            ]
        ]);
    }

    public function saveDatabase(Request $request)
    {
        if (SetupStatus::isCompleted('database_configured')) {
            return response()->json([
                'success' => false,
                'message' => 'Database is already configured. Reconfiguration is not allowed.',
            ], 422);
        }

        $credentials = $request->validate([
            'host' => 'required|string',
            'port' => 'required|numeric',
            'database' => 'required|string',
            'username' => 'required|string',
            'password' => 'nullable|string',
        ]);

        // Persist credentials only in production (writing .env during `artisan serve`
        // triggers a server restart and breaks the XHR). In non-production we keep
        // them in memory so the wizard continues seamlessly while still letting
        // developers override values in the file manually if they wish.
        if (app()->environment('production')) {
            $this->envManager->saveDatabaseCredentials($credentials);
        } else {
            $connection = config('database.default');
            foreach ($credentials as $k => $v) {
                Config::set("database.connections.{$connection}." . strtolower($k), $v);
            }
        }

        // Test the connection
        try {
            $connection = config('database.default');
            DB::reconnect($connection);
            DB::connection($connection)->getPdo();

            SetupStatus::markCompleted('database_configured');

            // Always redirect for Inertia requests
            if ($request->header('X-Inertia', false)) {
                return redirect()->route('setup.database');
            }

            $message = SetupStatus::isCompleted('database_migrated')
                ? 'Database connection updated successfully.'
                : 'Database connection successful.';

            return response()->json(['success' => true, 'message' => $message]);

        } catch (\Exception $e) {
            if ($request->header('X-Inertia', false)) {
                return redirect()->route('setup.database')->withErrors(['database' => 'Database connection failed: ' . $e->getMessage()]);
            }
            return response()->json(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()], 500);
        }
    }

    public function runMigrations(Request $request)
    {
        // Prevent re-execution if already completed
        if (SetupStatus::isCompleted('database_migrated')) {
            return response()->json([
                'message' => 'Database migrations already completed.',
            ], 422);
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
            SetupStatus::markCompleted('database_migrated');

            if ($request->header('X-Inertia', false)) {
                return redirect()->route('setup.roles_seeded');
            }

            return response()->json(['success' => true, 'message' => 'Database migrations completed.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Migration failed: ' . $e->getMessage()], 500);
        }
    }

    public function seedRolesAndPermissions(Request $request)
    {
        // Prevent re-execution if already completed
        if (SetupStatus::isCompleted('roles_seeded')) {
            return response()->json([
                'message' => 'Roles and permissions have already been seeded.',
            ], 422);
        }

        try {
            Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
            SetupStatus::markCompleted('roles_seeded');

            if ($request->header('X-Inertia', false)) {
                return redirect()->route('setup.app_settings');
            }

            return response()->json(['success' => true, 'message' => 'Roles and permissions seeded.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Seeding failed: ' . $e->getMessage()], 500);
        }
    }

    public function showAppSettings()
    {
        // Validate that prerequisites are met before showing this step
        if (!SetupStatus::isCompleted('roles_seeded')) {
            return redirect()->route('setup.index');
        }

        return Inertia::render('setup/index', [
            'currentStep' => 'app_settings',
            'steps' => $this->getStepsStatus(),
            'data' => [
                'app_name' => config('app.name'),
                'app_url' => config('app.url'),
            ]
        ]);
    }

    public function showRolesSeeded()
    {
        // Validate that prerequisites are met before showing this step
        if (!SetupStatus::isCompleted('database_migrated')) {
            return redirect()->route('setup.index');
        }

        return Inertia::render('setup/index', [
            'currentStep' => 'roles_seeded',
            'steps' => $this->getStepsStatus(),
            'data' => []
        ]);
    }

    public function createAdmin(CreateAdminRequest $request)
    {
        // Prevent creating multiple admin users during setup
        if (SetupStatus::isCompleted('admin_created')) {
            return response()->json([
                'message' => 'An administrator account has already been created.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($request) {
                // Normalize form data
                $validated = $request->validated();
                $appName = trim($validated['helpdesk_name']);
                $appUrl = trim($validated['helpdesk_url']);
                $adminName = trim($validated['name']);
                $adminEmail = strtolower(trim($validated['email']));

                // Persist app settings only in production to avoid dev-server restarts
                // caused by .env file writes while running `artisan serve`.
                if (app()->environment('production')) {
                    $this->envManager->saveAppSettings([
                        'APP_NAME' => $appName,
                        'APP_URL' => $appUrl,
                    ]);

                    // Rebuild the configuration cache for production deployments.
                    Artisan::call('config:clear');
                    Artisan::call('config:cache');
                } else {
                    // During local development simply update the in-memory config so
                    // the rest of this request (and subsequent ones) reflect the new
                    // values without touching the .env file.
                    Config::set('app.name', $appName);
                    Config::set('app.url', $appUrl);
                }

                $admin = User::create([
                    'name' => $adminName,
                    'email' => $adminEmail,
                    'password' => Hash::make($validated['password']),
                    'email_verified_at' => now(),
                ]);

                $admin->assignRole('system_administrator');

                SetupStatus::markCompleted('admin_created', [
                    'admin_id' => $admin->id,
                    'admin_email' => $admin->email,
                ]);
            });

            if ($request->header('X-Inertia', false)) {
                return redirect()->route('setup.complete');
            }
            return response()->json(['success' => true, 'message' => 'Administrator created.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Admin creation failed: ' . $e->getMessage()], 500);
        }
    }

    public function completeSetup(Request $request)
    {
        try {
            SetupStatus::markCompleted('setup_completed');
            $this->cleanupSetupSystem();

            Log::info('Support Center setup completed successfully. ' . now()->toISOString());

            if ($request->header('X-Inertia', false)) {
                // Inertia requires a redirect for POST/PUT/PATCH/DELETE success.
                return redirect()->route('login');
            }

            return response()->json([
                'success' => true,
                'message' => 'Setup completed!',
                'redirect' => route('login'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Setup completion failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reset the entire setup wizard (accessible while setup is in progress).
     */
    public function resetSetup(Request $request)
    {
        // A simple confirmation token prevents CSRF + accidental resets.
        $request->validate([
            'confirm' => 'required|in:yes',
        ]);

        $this->setupResetService->reset(null, 'user_restart_during_setup', [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Redirect back to the first step
        return redirect()->route('setup.prerequisites');
    }

    /**
     * Finalize setup when a GET request to /setup/complete is received.
     *
     * This prevents an edge-case redirect loop that can occur if the wizard
     * completed every prior step except the explicit POST to /setup/complete.
     * In that situation:
     *   1. /setup redirects the browser to /setup/complete (because all core
     *      steps appear finished).
     *   2. The GET /setup/complete handler immediately redirects to /login.
     *   3. The /login route is protected by the setup.completed middleware
     *      which, seeing that the final "setup_completed" flag is still FALSE,
     *      sends the user back to /setup – resulting in an infinite loop.
     *
     * By idempotently marking the wizard as completed here (only if it has not
     * been marked already) and invoking the same cleanup routine executed by
     * the POST controller, we ensure that the lock-file is written and the
     * middleware allows access to /login on the first redirect.
     */
    public function showComplete(Request $request)
    {
        // If the final step hasn't run yet, complete it now.
        if (!SetupStatus::isSetupCompleted()) {
            SetupStatus::markCompleted('setup_completed');
            $this->cleanupSetupSystem();
            Log::info('Support Center setup completed via GET /setup/complete.');
        }

        return redirect()->route('login');
    }

    private function getStepsStatus(): array
    {
        return [
            'prerequisites_checked' => SetupStatus::isCompleted('prerequisites_checked'),
            'database_configured' => SetupStatus::isCompleted('database_configured'),
            'database_migrated' => SetupStatus::isCompleted('database_migrated'),
            'roles_seeded' => SetupStatus::isCompleted('roles_seeded'),
            'admin_created' => SetupStatus::isCompleted('admin_created'),
        ];
    }

    private function getCurrentStep(): string
    {
        if (!SetupStatus::isCompleted('prerequisites_checked'))
            return 'prerequisites';
        if (!$this->isDatabaseConnected() || !SetupStatus::isCompleted('database_configured'))
            return 'database';
        if (!SetupStatus::isCompleted('database_migrated'))
            return 'database'; // Migrations happen on db page
        if (!SetupStatus::isCompleted('roles_seeded'))
            return 'roles_seeded'; // Fixed: proper step name
        if (!SetupStatus::isCompleted('admin_created'))
            return 'app_settings';
        return 'complete';
    }

    private function isDatabaseConnected(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function cleanupSetupSystem(): void
    {
        // Create a flag file to disable setup routes
        $setupLockFile = storage_path('app/setup.lock');
        file_put_contents($setupLockFile, json_encode([
            'completed_at' => now()->toISOString(),
            'completed_by' => 'setup_wizard',
            'version' => config('app.version', '1.0.0'),
        ]));

        // Make .env file read-only for security
        try {
            chmod($this->envManager->getEnvPath(), 0444);
        } catch (\Exception $e) {
            Log::warning('Could not make .env file read-only.', ['error' => $e->getMessage()]);
        }

        Cache::flush();

        if (app()->environment('production')) {
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
        }
    }
}
