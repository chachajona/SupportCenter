<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Department;
use App\Models\SetupStatus;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $this->createDepartments();
            $this->createPermissions();
            $this->createRoles();
            $this->assignPermissionsToRoles();

            // Mark seeding as completed
            SetupStatus::markCompleted('roles_seeded');
            SetupStatus::markCompleted('permissions_seeded');
        });
    }

    /**
     * Create basic departments.
     */
    private function createDepartments(): void
    {
        $departments = [
            ['name' => 'IT Support', 'path' => '/'],
            ['name' => 'Customer Service', 'path' => '/'],
            ['name' => 'Technical Support', 'path' => '/'],
            ['name' => 'Management', 'path' => '/'],
            ['name' => 'Quality Assurance', 'path' => '/'],
        ];

        foreach ($departments as $dept) {
            Department::firstOrCreate(['name' => $dept['name']], $dept);
        }
    }

    /**
     * Create comprehensive permission structure
     */
    private function createPermissions(): void
    {
        $permissions = [
            // Ticket Management
            'tickets' => [
                'create',
                'view_own',
                'view_department',
                'view_all',
                'edit_own',
                'edit_department',
                'edit_all',
                'delete_own',
                'delete_department',
                'delete_all',
                'assign',
                'transfer',
                'close',
                'reopen'
            ],

            // User Management
            'users' => [
                'view_own',
                'view_department',
                'view_all',
                'create',
                'edit_own',
                'edit_department',
                'edit_all',
                'delete',
                'manage_roles',
                'impersonate'
            ],

            // Role Management
            'roles' => [
                'view',
                'create',
                'edit',
                'delete',
                'assign',
                'revoke',
                'assign_temporal',
                'revoke_temporal',
                'request_temporal',
                'approve_temporal',
                'deny_temporal',
                'view_matrix',
                'edit_matrix'
            ],

            // Department Management
            'departments' => [
                'view_own',
                'view_all',
                'create',
                'edit',
                'delete',
                'manage_hierarchy'
            ],

            // System Administration
            'system' => [
                'configuration',
                'maintenance',
                'backup',
                'logs_view',
                'plugins_manage',
                'manage'
            ],

            // Audit and Compliance
            'audit' => [
                'view_logs',
                'export_data',
                'compliance_reports',
                'view'
            ],

            // Knowledge Management
            'knowledge' => [
                'create_articles',
                'edit_articles',
                'approve_articles',
                'manage_categories',
                'version_control'
            ],

            // SLA Management
            'sla' => [
                'view',
                'create',
                'edit',
                'enforce',
                'escalate'
            ],

            // Reports and Analytics
            'reports' => [
                'view_own',
                'view_department',
                'view_all',
                'create_custom',
                'export',
                'schedule'
            ],

            // Analytics (RBAC Dashboard)
            'analytics' => [
                'view',
                'view_own',
                'view_department',
                'view_all',
                'export'
            ],

            // Monitoring
            'monitoring' => [
                'view',
                'export'
            ],

            // Emergency Access Management
            'emergency' => [
                'view',
                'grant',
                'revoke',
                'manage'
            ],

            // Permissions
            'permissions' => [
                'view',
                'create',
                'edit',
                'delete'
            ]
        ];

        foreach ($permissions as $resource => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$resource}.{$action}",
                    'guard_name' => 'web',
                ], [
                    'display_name' => ucwords(str_replace('_', ' ', "{$resource} {$action}")),
                    'description' => "Permission to {$action} {$resource}",
                    'resource' => $resource,
                    'action' => $action,
                ]);
            }
        }
    }

    /**
     * Create the six default roles with hierarchy
     */
    private function createRoles(): void
    {
        $roles = [
            'support_agent' => [
                'display_name' => 'Support Agent',
                'description' => 'Basic support staff with limited access to assigned tickets',
                'hierarchy_level' => 1,
            ],
            'department_manager' => [
                'display_name' => 'Department Manager',
                'description' => 'Manages department operations and team performance',
                'hierarchy_level' => 2,
            ],
            'regional_manager' => [
                'display_name' => 'Regional Manager',
                'description' => 'Oversees multiple departments and regional operations',
                'hierarchy_level' => 3,
            ],
            'system_administrator' => [
                'display_name' => 'System Administrator',
                'description' => 'Full system access and configuration management',
                'hierarchy_level' => 4,
            ],
            'compliance_auditor' => [
                'display_name' => 'Compliance Auditor',
                'description' => 'Read-only access for compliance and audit purposes',
                'hierarchy_level' => 3,
            ],
            'knowledge_curator' => [
                'display_name' => 'Knowledge Curator',
                'description' => 'Manages knowledge base and content',
                'hierarchy_level' => 2,
            ]
        ];

        foreach ($roles as $roleName => $roleData) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ], [
                'display_name' => $roleData['display_name'],
                'description' => $roleData['description'],
                'hierarchy_level' => $roleData['hierarchy_level'],
                'is_active' => true,
            ]);
        }
    }

    /**
     * Assign permissions to roles with inheritance
     */
    private function assignPermissionsToRoles(): void
    {
        $rolePermissions = [
            'support_agent' => [
                'tickets.create',
                'tickets.view_own',
                'tickets.edit_own',
                'tickets.assign',
                'tickets.transfer',
                'tickets.close',
                'users.view_own',
                'users.edit_own',
                'departments.view_own',
                'knowledge.create_articles',
                'reports.view_own'
            ],

            'department_manager' => [
                // Inherits all support_agent permissions plus:
                'tickets.view_department',
                'tickets.edit_department',
                'tickets.delete_department',
                'tickets.reopen',
                'users.view_department',
                'users.edit_department',
                'users.create',
                'users.manage_roles',
                'departments.view_all',
                'departments.edit',
                'sla.view',
                'sla.enforce',
                'sla.escalate',
                'reports.view_department',
                'reports.create_custom',
                'knowledge.edit_articles',
                'knowledge.approve_articles',
                // NEW: Analytics
                'analytics.view_department'
            ],

            'regional_manager' => [
                // Inherits department_manager permissions plus:
                'tickets.view_all',
                'tickets.edit_all',
                'users.view_all',
                'users.edit_all',
                'departments.create',
                'departments.manage_hierarchy',
                'sla.create',
                'sla.edit',
                'reports.view_all',
                'reports.export',
                'reports.schedule',
                // NEW: Analytics
                'analytics.view_all',
                'analytics.export'
            ],

            'system_administrator' => [
                // All permissions - handled specially
            ],

            'compliance_auditor' => [
                'tickets.view_all',
                'users.view_all',
                'departments.view_all',
                'roles.view',
                'audit.view_logs',
                'audit.export_data',
                'audit.compliance_reports',
                'reports.view_all',
                'reports.export'
            ],

            'knowledge_curator' => [
                'tickets.view_department',
                'users.view_department',
                'knowledge.create_articles',
                'knowledge.edit_articles',
                'knowledge.approve_articles',
                'knowledge.manage_categories',
                'knowledge.version_control',
                'reports.view_department'
            ]
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->first();
            if (!$role)
                continue;

            // Handle system administrator special case
            if ($roleName === 'system_administrator') {
                $role->syncPermissions(Permission::all());
            } else {
                // Handle permission inheritance
                $allPermissions = $this->getInheritedPermissions($roleName, $permissions, $rolePermissions);
                $role->syncPermissions($allPermissions);
            }
        }
    }

    /**
     * Get inherited permissions based on role hierarchy
     */
    private function getInheritedPermissions(string $roleName, array $permissions, array $allRolePermissions): array
    {
        $inherited = [];

        // Define inheritance hierarchy
        $inheritance = [
            'department_manager' => ['support_agent'],
            'regional_manager' => ['department_manager', 'support_agent'],
        ];

        // Add inherited permissions
        if (isset($inheritance[$roleName])) {
            foreach ($inheritance[$roleName] as $parentRole) {
                if (isset($allRolePermissions[$parentRole])) {
                    $inherited = array_merge($inherited, $allRolePermissions[$parentRole]);
                }
            }
        }

        // Merge with role-specific permissions
        return array_unique(array_merge($inherited, $permissions));
    }
}
