<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PermissionCacheService;
use App\Services\TemporalAccessService;
use App\Services\EmergencyAccessService;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RBACHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rbac:health-check {--detailed : Show detailed output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run comprehensive RBAC system health checks';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Running RBAC Health Checks...');
        $this->newLine();

        $issues = 0;

        // Check cache performance
        $issues += $this->checkCachePerformance();

        // Verify role consistency
        $issues += $this->verifyRoleConsistency();

        // Test permission inheritance
        $issues += $this->testPermissionInheritance();

        // Check expired permissions
        $issues += $this->checkExpiredPermissions();

        // Verify database integrity
        $issues += $this->verifyDatabaseIntegrity();

        // Check emergency access
        $issues += $this->checkEmergencyAccess();

        $this->newLine();

        if ($issues === 0) {
            $this->info('✅ All RBAC health checks passed!');
            return Command::SUCCESS;
        } else {
            $this->error("❌ Found {$issues} issue(s) in RBAC system");
            return Command::FAILURE;
        }
    }

    /**
     * Check cache performance and hit ratios.
     */
    private function checkCachePerformance(): int
    {
        $this->info('📊 Checking cache performance...');
        $issues = 0;

        try {
            $cacheService = app(PermissionCacheService::class);
            $stats = $cacheService->getCacheStats();

            if ($this->option('detailed')) {
                $this->line("Cache store: {$stats['store']}");
                $this->line("Cache prefix: {$stats['prefix']}");
                $this->line("TTL: {$stats['ttl']} seconds");
                $this->line("Tags supported: " . ($stats['tags_supported'] ? 'Yes' : 'No'));
            }

            // Test cache performance with a sample user
            $sampleUser = User::first();
            if ($sampleUser) {
                $startTime = microtime(true);
                $cacheService->getUserPermissions($sampleUser->id);
                $endTime = microtime(true);

                $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

                if ($responseTime > 50) {
                    $this->warn("⚠️  Cache response time is slow: {$responseTime}ms (target: <50ms)");
                    $issues++;
                } else {
                    $this->line("✅ Cache response time: {$responseTime}ms");
                }
            } else {
                $this->warn("⚠️  No users found in database - skipping cache performance test");
                $this->line("💡 Create users to enable cache performance testing");
            }

        } catch (\Exception $e) {
            $this->error("❌ Cache performance check failed: " . $e->getMessage());
            $issues++;
        }

        return $issues;
    }

    /**
     * Verify role consistency and hierarchy.
     */
    private function verifyRoleConsistency(): int
    {
        $this->info('🏗️  Verifying role consistency...');
        $issues = 0;

        // Check for duplicate role names
        $duplicateRoles = DB::table('roles')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicateRoles->count() > 0) {
            $this->error("❌ Found duplicate role names: " . $duplicateRoles->pluck('name')->implode(', '));
            $issues++;
        } else {
            $this->line('✅ No duplicate role names found');
        }

        // Check role hierarchy consistency
        $roles = Role::orderBy('hierarchy_level')->get();
        $expectedRoles = [
            'support_agent' => 1,
            'department_manager' => 2,
            'regional_manager' => 3,
            'system_administrator' => 4,
            'compliance_auditor' => 2,
            'knowledge_curator' => 2,
        ];

        foreach ($expectedRoles as $roleName => $expectedLevel) {
            $role = $roles->where('name', $roleName)->first();
            if (!$role) {
                $this->error("❌ Missing required role: {$roleName}");
                $issues++;
            } elseif ($role->hierarchy_level !== $expectedLevel) {
                $this->warn("⚠️  Role {$roleName} has incorrect hierarchy level: {$role->hierarchy_level} (expected: {$expectedLevel})");
                $issues++;
            }
        }

        if ($this->option('detailed')) {
            $this->table(
                ['Role', 'Hierarchy Level', 'Active', 'Users Count'],
                $roles->map(function ($role) {
                    return [
                        $role->name,
                        $role->hierarchy_level,
                        $role->is_active ? 'Yes' : 'No',
                        $role->users()->count(),
                    ];
                })->toArray()
            );
        }

        return $issues;
    }

    /**
     * Test permission inheritance between roles.
     */
    private function testPermissionInheritance(): int
    {
        $this->info('🔗 Testing permission inheritance...');
        $issues = 0;

        try {
            // Define specific inheritance rules - roles that should have more permissions than others
            $inheritanceRules = [
                'department_manager' => ['support_agent'], // Manager should have all agent permissions + more
                'regional_manager' => ['department_manager', 'support_agent'], // Regional includes department + agent
                'system_administrator' => ['regional_manager', 'department_manager', 'support_agent'], // Admin has all
            ];

            foreach ($inheritanceRules as $higherRole => $lowerRoles) {
                $higherRoleModel = Role::where('name', $higherRole)->first();
                if (!$higherRoleModel) {
                    $this->warn("⚠️  Role '{$higherRole}' not found for inheritance testing");
                    continue;
                }

                $higherPermissions = $higherRoleModel->permissions()->pluck('name')->toArray();

                foreach ($lowerRoles as $lowerRole) {
                    $lowerRoleModel = Role::where('name', $lowerRole)->first();
                    if (!$lowerRoleModel) {
                        $this->warn("⚠️  Role '{$lowerRole}' not found for inheritance testing");
                        continue;
                    }

                    $lowerPermissions = $lowerRoleModel->permissions()->pluck('name')->toArray();
                    $missingPermissions = array_diff($lowerPermissions, $higherPermissions);

                    if (!empty($missingPermissions)) {
                        $this->warn("⚠️  Role '{$higherRole}' missing permissions from '{$lowerRole}': " . implode(', ', $missingPermissions));
                        $issues++;
                    }
                }
            }

            // Test specialized roles have appropriate permissions
            $specializedRoles = [
                'compliance_auditor' => ['tickets.view_all', 'audit.view_logs'],
                'knowledge_curator' => ['knowledge.create', 'knowledge.edit', 'knowledge.approve', 'knowledge.delete'],
            ];

            foreach ($specializedRoles as $roleName => $requiredPermissions) {
                $role = Role::where('name', $roleName)->first();
                if ($role) {
                    $rolePermissions = $role->permissions()->pluck('name')->toArray();
                    $missingRequired = array_diff($requiredPermissions, $rolePermissions);

                    if (!empty($missingRequired)) {
                        $this->warn("⚠️  Specialized role '{$roleName}' missing required permissions: " . implode(', ', $missingRequired));
                        $issues++;
                    }
                }
            }

            if ($this->option('detailed')) {
                $roles = Role::with('permissions')->get();
                foreach ($roles as $role) {
                    $this->line("{$role->name}: {$role->permissions->count()} permissions");
                }
            }

        } catch (\Exception $e) {
            $this->error("❌ Permission inheritance check failed: " . $e->getMessage());
            $issues++;
        }

        return $issues;
    }

    /**
     * Check for expired permissions and clean them up.
     */
    private function checkExpiredPermissions(): int
    {
        $this->info('⏰ Checking expired permissions...');
        $issues = 0;

        try {
            $temporalService = app(TemporalAccessService::class);
            $expiredCount = $temporalService->cleanupExpiredPermissions();

            if ($expiredCount > 0) {
                $this->warn("⚠️  Cleaned up {$expiredCount} expired permission(s)");
            } else {
                $this->line('✅ No expired permissions found');
            }

            // Check for expiring permissions
            $expiringRoles = $temporalService->getExpiringRoles(60); // Within 1 hour
            if (count($expiringRoles) > 0) {
                $this->warn("⚠️  " . count($expiringRoles) . " role(s) expiring within 1 hour");
                if ($this->option('detailed')) {
                    foreach ($expiringRoles as $role) {
                        $this->line("  - {$role->user_name} ({$role->role_name}) expires at {$role->expires_at}");
                    }
                }
            }

        } catch (\Exception $e) {
            $this->error("❌ Expired permissions check failed: " . $e->getMessage());
            $issues++;
        }

        return $issues;
    }

    /**
     * Verify database integrity.
     */
    private function verifyDatabaseIntegrity(): int
    {
        $this->info('🗄️  Verifying database integrity...');
        $issues = 0;

        try {
            // Check for orphaned role assignments
            $orphanedRoleUsers = DB::table('role_user')
                ->leftJoin('users', 'role_user.user_id', '=', 'users.id')
                ->leftJoin('roles', 'role_user.role_id', '=', 'roles.id')
                ->whereNull('users.id')
                ->orWhereNull('roles.id')
                ->count();

            if ($orphanedRoleUsers > 0) {
                $this->error("❌ Found {$orphanedRoleUsers} orphaned role assignment(s)");
                $issues++;
            } else {
                $this->line('✅ No orphaned role assignments found');
            }

            // Check for orphaned permission assignments
            $orphanedPermissionRoles = DB::table(config('permission.table_names.role_has_permissions'))
                ->leftJoin('permissions', config('permission.table_names.role_has_permissions') . '.permission_id', '=', 'permissions.id')
                ->leftJoin('roles', config('permission.table_names.role_has_permissions') . '.role_id', '=', 'roles.id')
                ->whereNull('permissions.id')
                ->orWhereNull('roles.id')
                ->count();

            if ($orphanedPermissionRoles > 0) {
                $this->error("❌ Found {$orphanedPermissionRoles} orphaned permission assignment(s)");
                $issues++;
            } else {
                $this->line('✅ No orphaned permission assignments found');
            }

        } catch (\Exception $e) {
            $this->error("❌ Database integrity check failed: " . $e->getMessage());
            $issues++;
        }

        return $issues;
    }

    /**
     * Check emergency access status.
     */
    private function checkEmergencyAccess(): int
    {
        $this->info('🚨 Checking emergency access...');
        $issues = 0;

        try {
            $emergencyService = app(EmergencyAccessService::class);
            $expiredCount = $emergencyService->cleanupExpiredEmergencyAccess();

            if ($expiredCount > 0) {
                $this->warn("⚠️  Cleaned up {$expiredCount} expired emergency access record(s)");
            }

            $stats = $emergencyService->getEmergencyAccessStats(7); // Last 7 days

            if ($this->option('detailed')) {
                $this->line("Emergency access stats (last 7 days):");
                $this->line("  - Total granted: {$stats['total_granted']}");
                $this->line("  - Currently active: {$stats['currently_active']}");
                $this->line("  - Used access: {$stats['used_access']}");
                $this->line("  - Expired access: {$stats['expired_access']}");
            }

            if ($stats['currently_active'] > 5) {
                $this->warn("⚠️  High number of active emergency access records: {$stats['currently_active']}");
                $issues++;
            }

        } catch (\Exception $e) {
            $this->error("❌ Emergency access check failed: " . $e->getMessage());
            $issues++;
        }

        return $issues;
    }
}
