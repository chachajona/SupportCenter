<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Department;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create departments first
        $this->createDepartments();

        // Create permissions
        $permissions = $this->createPermissions();

        // Create roles
        $roles = $this->createRoles();

        // Assign permissions to roles
        $this->assignPermissionsToRoles($roles, $permissions);

        $this->command->info('RBAC roles and permissions seeded successfully!');
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
     * Create all permissions.
     */
    private function createPermissions(): array
    {
        $permissions = [
            // Ticket permissions
            ['name' => 'tickets.view_own', 'display_name' => 'View Own Tickets', 'resource' => 'tickets', 'action' => 'view_own'],
            ['name' => 'tickets.view_department', 'display_name' => 'View Department Tickets', 'resource' => 'tickets', 'action' => 'view_department'],
            ['name' => 'tickets.view_all', 'display_name' => 'View All Tickets', 'resource' => 'tickets', 'action' => 'view_all'],
            ['name' => 'tickets.create', 'display_name' => 'Create Tickets', 'resource' => 'tickets', 'action' => 'create'],
            ['name' => 'tickets.edit_own', 'display_name' => 'Edit Own Tickets', 'resource' => 'tickets', 'action' => 'edit_own'],
            ['name' => 'tickets.edit_department', 'display_name' => 'Edit Department Tickets', 'resource' => 'tickets', 'action' => 'edit_department'],
            ['name' => 'tickets.edit_all', 'display_name' => 'Edit All Tickets', 'resource' => 'tickets', 'action' => 'edit_all'],
            ['name' => 'tickets.delete_own', 'display_name' => 'Delete Own Tickets', 'resource' => 'tickets', 'action' => 'delete_own'],
            ['name' => 'tickets.delete_department', 'display_name' => 'Delete Department Tickets', 'resource' => 'tickets', 'action' => 'delete_department'],
            ['name' => 'tickets.delete_all', 'display_name' => 'Delete All Tickets', 'resource' => 'tickets', 'action' => 'delete_all'],
            ['name' => 'tickets.assign', 'display_name' => 'Assign Tickets', 'resource' => 'tickets', 'action' => 'assign'],
            ['name' => 'tickets.escalate', 'display_name' => 'Escalate Tickets', 'resource' => 'tickets', 'action' => 'escalate'],
            ['name' => 'tickets.close', 'display_name' => 'Close Tickets', 'resource' => 'tickets', 'action' => 'close'],

            // User management permissions
            ['name' => 'users.view_own', 'display_name' => 'View Own Profile', 'resource' => 'users', 'action' => 'view_own'],
            ['name' => 'users.view_department', 'display_name' => 'View Department Users', 'resource' => 'users', 'action' => 'view_department'],
            ['name' => 'users.view_all', 'display_name' => 'View All Users', 'resource' => 'users', 'action' => 'view_all'],
            ['name' => 'users.create', 'display_name' => 'Create Users', 'resource' => 'users', 'action' => 'create'],
            ['name' => 'users.edit_own', 'display_name' => 'Edit Own Profile', 'resource' => 'users', 'action' => 'edit_own'],
            ['name' => 'users.edit_department', 'display_name' => 'Edit Department Users', 'resource' => 'users', 'action' => 'edit_department'],
            ['name' => 'users.edit_all', 'display_name' => 'Edit All Users', 'resource' => 'users', 'action' => 'edit_all'],
            ['name' => 'users.delete', 'display_name' => 'Delete Users', 'resource' => 'users', 'action' => 'delete'],

            // Role and permission management
            ['name' => 'roles.view', 'display_name' => 'View Roles', 'resource' => 'roles', 'action' => 'view'],
            ['name' => 'roles.create', 'display_name' => 'Create Roles', 'resource' => 'roles', 'action' => 'create'],
            ['name' => 'roles.edit', 'display_name' => 'Edit Roles', 'resource' => 'roles', 'action' => 'edit'],
            ['name' => 'roles.delete', 'display_name' => 'Delete Roles', 'resource' => 'roles', 'action' => 'delete'],
            ['name' => 'roles.assign', 'display_name' => 'Assign Roles', 'resource' => 'roles', 'action' => 'assign'],

            // Reports and analytics
            ['name' => 'reports.view_basic', 'display_name' => 'View Basic Reports', 'resource' => 'reports', 'action' => 'view_basic'],
            ['name' => 'reports.view_department', 'display_name' => 'View Department Reports', 'resource' => 'reports', 'action' => 'view_department'],
            ['name' => 'reports.view_all', 'display_name' => 'View All Reports', 'resource' => 'reports', 'action' => 'view_all'],
            ['name' => 'reports.create_custom', 'display_name' => 'Create Custom Reports', 'resource' => 'reports', 'action' => 'create_custom'],
            ['name' => 'reports.export', 'display_name' => 'Export Reports', 'resource' => 'reports', 'action' => 'export'],

            // Knowledge base management
            ['name' => 'knowledge.view', 'display_name' => 'View Knowledge Base', 'resource' => 'knowledge', 'action' => 'view'],
            ['name' => 'knowledge.create', 'display_name' => 'Create Articles', 'resource' => 'knowledge', 'action' => 'create'],
            ['name' => 'knowledge.edit', 'display_name' => 'Edit Articles', 'resource' => 'knowledge', 'action' => 'edit'],
            ['name' => 'knowledge.approve', 'display_name' => 'Approve Articles', 'resource' => 'knowledge', 'action' => 'approve'],
            ['name' => 'knowledge.delete', 'display_name' => 'Delete Articles', 'resource' => 'knowledge', 'action' => 'delete'],

            // System administration
            ['name' => 'system.configuration', 'display_name' => 'System Configuration', 'resource' => 'system', 'action' => 'configuration'],
            ['name' => 'system.maintenance', 'display_name' => 'System Maintenance', 'resource' => 'system', 'action' => 'maintenance'],
            ['name' => 'system.plugins', 'display_name' => 'Plugin Management', 'resource' => 'system', 'action' => 'plugins'],
            ['name' => 'system.backup', 'display_name' => 'System Backup', 'resource' => 'system', 'action' => 'backup'],

            // Audit and compliance
            ['name' => 'audit.view_logs', 'display_name' => 'View Audit Logs', 'resource' => 'audit', 'action' => 'view_logs'],
            ['name' => 'audit.export_data', 'display_name' => 'Export Audit Data', 'resource' => 'audit', 'action' => 'export_data'],
            ['name' => 'audit.compliance_reports', 'display_name' => 'Compliance Reports', 'resource' => 'audit', 'action' => 'compliance_reports'],

            // SLA management
            ['name' => 'sla.view', 'display_name' => 'View SLA Policies', 'resource' => 'sla', 'action' => 'view'],
            ['name' => 'sla.manage', 'display_name' => 'Manage SLA Policies', 'resource' => 'sla', 'action' => 'manage'],
            ['name' => 'sla.enforce', 'display_name' => 'Enforce SLA Policies', 'resource' => 'sla', 'action' => 'enforce'],
        ];

        $createdPermissions = [];
        foreach ($permissions as $permission) {
            $createdPermissions[$permission['name']] = Permission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }

        return $createdPermissions;
    }

    /**
     * Create the 6 default roles.
     */
    private function createRoles(): array
    {
        $roles = [
            [
                'name' => 'support_agent',
                'display_name' => 'Support Agent',
                'description' => 'Front-line support staff handling ticket resolution',
                'hierarchy_level' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'department_manager',
                'display_name' => 'Department Manager',
                'description' => 'Manages team operations and departmental oversight',
                'hierarchy_level' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'regional_manager',
                'display_name' => 'Regional Manager',
                'description' => 'Oversees multiple departments and regional operations',
                'hierarchy_level' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'system_administrator',
                'display_name' => 'System Administrator',
                'description' => 'Full system access and configuration management',
                'hierarchy_level' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'compliance_auditor',
                'display_name' => 'Compliance Auditor',
                'description' => 'Read-only access for compliance and audit purposes',
                'hierarchy_level' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'knowledge_curator',
                'display_name' => 'Knowledge Curator',
                'description' => 'Manages knowledge base and content approval',
                'hierarchy_level' => 2,
                'is_active' => true,
            ],
        ];

        $createdRoles = [];
        foreach ($roles as $role) {
            $createdRoles[$role['name']] = Role::firstOrCreate(
                ['name' => $role['name']],
                $role
            );
        }

        return $createdRoles;
    }

    /**
     * Assign permissions to roles based on role hierarchy.
     */
    private function assignPermissionsToRoles(array $roles, array $permissions): void
    {
        // Support Agent permissions
        $roles['support_agent']->syncPermissions([
            $permissions['tickets.view_own'],
            $permissions['tickets.create'],
            $permissions['tickets.edit_own'],
            $permissions['tickets.close'],
            $permissions['users.view_own'],
            $permissions['users.edit_own'],
            $permissions['knowledge.view'],
            $permissions['reports.view_basic'],
        ]);

        // Department Manager permissions (inherits Support Agent + additional)
        $departmentManagerPermissions = [
            // Support Agent permissions
            $permissions['tickets.view_own'],
            $permissions['tickets.create'],
            $permissions['tickets.edit_own'],
            $permissions['tickets.close'],
            $permissions['users.view_own'],
            $permissions['users.edit_own'],
            $permissions['knowledge.view'],
            $permissions['reports.view_basic'],
            // Additional permissions
            $permissions['tickets.view_department'],
            $permissions['tickets.edit_department'],
            $permissions['tickets.assign'],
            $permissions['tickets.escalate'],
            $permissions['users.view_department'],
            $permissions['users.edit_department'],
            $permissions['reports.view_department'],
            $permissions['sla.view'],
            $permissions['sla.enforce'],
        ];
        $roles['department_manager']->syncPermissions($departmentManagerPermissions);

        // Regional Manager permissions
        $regionalManagerPermissions = array_merge($departmentManagerPermissions, [
            $permissions['tickets.view_all'],
            $permissions['tickets.edit_all'],
            $permissions['users.view_all'],
            $permissions['users.create'],
            $permissions['reports.view_all'],
            $permissions['reports.create_custom'],
            $permissions['reports.export'],
            $permissions['roles.view'],
            $permissions['roles.assign'],
        ]);
        $roles['regional_manager']->syncPermissions($regionalManagerPermissions);

        // System Administrator permissions (all permissions)
        $roles['system_administrator']->syncPermissions(array_values($permissions));

        // Compliance Auditor permissions (read-only)
        $roles['compliance_auditor']->syncPermissions([
            $permissions['tickets.view_all'],
            $permissions['users.view_all'],
            $permissions['audit.view_logs'],
            $permissions['audit.export_data'],
            $permissions['audit.compliance_reports'],
            $permissions['reports.view_all'],
            $permissions['reports.export'],
            $permissions['knowledge.view'],
            $permissions['roles.view'],
        ]);

        // Knowledge Curator permissions
        $roles['knowledge_curator']->syncPermissions([
            $permissions['tickets.view_own'],
            $permissions['users.view_own'],
            $permissions['users.edit_own'],
            $permissions['knowledge.view'],
            $permissions['knowledge.create'],
            $permissions['knowledge.edit'],
            $permissions['knowledge.approve'],
            $permissions['knowledge.delete'],
            $permissions['reports.view_basic'],
        ]);
    }
}
