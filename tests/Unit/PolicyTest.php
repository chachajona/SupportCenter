<?php

namespace Tests\Unit;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createBasicRoles();
        $this->createBasicPermissions();
        $this->createTestDepartments();
    }

    #[Test]
    public function user_policy_allows_users_to_view_themselves()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'support_agent')->first();
        $user->assignRole($role);

        $this->assertTrue($user->can('view', $user));
    }

    #[Test]
    public function user_policy_allows_department_scoped_viewing()
    {
        $department = Department::where('name', 'IT Support')->first();
        $user1 = User::factory()->create(['department_id' => $department->id]);
        $user2 = User::factory()->create(['department_id' => $department->id]);
        $user3 = User::factory()->create(); // Different department

        $permission = Permission::where('name', 'users.view_department')->first();
        $role = Role::where('name', 'support_agent')->first();
        $role->givePermissionTo($permission);
        $user1->assignRole($role);

        $this->assertTrue($user1->can('view', $user2)); // Same department
        $this->assertFalse($user1->can('view', $user3)); // Different department
    }

    #[Test]
    public function user_policy_prevents_self_role_assignment()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'support_agent')->first();
        $user->assignRole($role);

        $this->assertFalse($user->can('assignRole', $user));
    }

    #[Test]
    public function user_policy_allows_role_assignment_with_permission()
    {
        $admin = User::factory()->create();
        $targetUser = User::factory()->create();

        $permission = Permission::create([
            'name' => 'roles.assign_all',
            'resource' => 'roles',
            'action' => 'assign_all',
        ]);

        $role = Role::where('name', 'system_administrator')->first();
        $role->givePermissionTo($permission);
        $admin->assignRole($role);

        $this->assertTrue($admin->can('assignRole', $targetUser));
    }

    #[Test]
    public function user_policy_prevents_deletion_of_self()
    {
        $user = User::factory()->create();
        $permission = Permission::create([
            'name' => 'users.delete_all',
            'resource' => 'users',
            'action' => 'delete_all',
        ]);

        $role = Role::where('name', 'system_administrator')->first();
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $this->assertFalse($user->can('delete', $user));
    }

    #[Test]
    public function user_policy_enforces_hierarchy_for_impersonation()
    {
        $lowUser = User::factory()->create();
        $highUser = User::factory()->create();

        $lowRole = Role::where('name', 'support_agent')->first(); // hierarchy_level = 1
        $highRole = Role::where('name', 'system_administrator')->first(); // hierarchy_level = 4

        $permission = Permission::where('name', 'users.impersonate')->first();
        $highRole->givePermissionTo($permission);

        $lowUser->assignRole($lowRole);
        $highUser->assignRole($highRole);

        // High hierarchy can impersonate low hierarchy
        $this->assertTrue($highUser->can('impersonate', $lowUser));
        // Low hierarchy cannot impersonate high hierarchy
        $this->assertFalse($lowUser->can('impersonate', $highUser));
    }

    #[Test]
    public function role_policy_prevents_updating_higher_hierarchy_roles()
    {
        $user = User::factory()->create();
        $lowerRole = Role::where('name', 'support_agent')->first(); // hierarchy_level = 1
        $higherRole = Role::where('name', 'system_administrator')->first(); // hierarchy_level = 4

        $permission = Permission::create([
            'name' => 'roles.update',
            'resource' => 'roles',
            'action' => 'update',
        ]);

        $lowerRole->givePermissionTo($permission);
        $user->assignRole($lowerRole);

        $this->assertFalse($user->can('update', $higherRole));
    }

    #[Test]
    public function role_policy_allows_updating_lower_hierarchy_roles()
    {
        $user = User::factory()->create();
        $higherRole = Role::where('name', 'system_administrator')->first(); // hierarchy_level = 4
        $lowerRole = Role::where('name', 'support_agent')->first(); // hierarchy_level = 1

        $permission = Permission::create([
            'name' => 'roles.update',
            'resource' => 'roles',
            'action' => 'update',
        ]);

        $higherRole->givePermissionTo($permission);
        $user->assignRole($higherRole);

        $this->assertTrue($user->can('update', $lowerRole));
    }

    #[Test]
    public function role_policy_prevents_assigning_higher_hierarchy_roles()
    {
        $user = User::factory()->create();
        $lowerRole = Role::where('name', 'department_manager')->first(); // hierarchy_level = 2
        $higherRole = Role::where('name', 'system_administrator')->first(); // hierarchy_level = 4

        $permission = Permission::create([
            'name' => 'roles.assign_all',
            'resource' => 'roles',
            'action' => 'assign_all',
        ]);

        $lowerRole->givePermissionTo($permission);
        $user->assignRole($lowerRole);

        $this->assertFalse($user->can('assign', $higherRole));
    }

    #[Test]
    public function department_policy_allows_managers_to_view_own_department()
    {
        $department = Department::where('name', 'IT Support')->first();
        $user = User::factory()->create(['department_id' => $department->id]);

        $role = Role::where('name', 'department_manager')->first();
        $user->assignRole($role);

        $this->assertTrue($user->can('view', $department));
    }

    #[Test]
    public function department_policy_allows_global_view_permission()
    {
        $department = Department::where('name', 'IT Support')->first();
        $user = User::factory()->create();

        $permission = Permission::create([
            'name' => 'departments.view_all',
            'resource' => 'departments',
            'action' => 'view_all',
        ]);

        $role = Role::where('name', 'system_administrator')->first();
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $this->assertTrue($user->can('view', $department));
    }

    #[Test]
    public function department_policy_prevents_deletion_with_users()
    {
        $department = Department::where('name', 'IT Support')->first();
        $user = User::factory()->create(['department_id' => $department->id]);
        $admin = User::factory()->create();

        $permission = Permission::create([
            'name' => 'departments.delete',
            'resource' => 'departments',
            'action' => 'delete',
        ]);

        $role = Role::where('name', 'system_administrator')->first();
        $role->givePermissionTo($permission);
        $admin->assignRole($role);

        // Should not be able to delete department with users
        $this->assertFalse($admin->can('delete', $department));
    }

    #[Test]
    public function department_policy_allows_user_management_for_own_department()
    {
        $department = Department::where('name', 'IT Support')->first();
        $manager = User::factory()->create(['department_id' => $department->id]);

        $permission = Permission::create([
            'name' => 'users.manage_own_department',
            'resource' => 'users',
            'action' => 'manage_own_department',
        ]);

        $role = Role::where('name', 'department_manager')->first();
        $role->givePermissionTo($permission);
        $manager->assignRole($role);

        $this->assertTrue($manager->can('manageUsers', $department));
    }

    #[Test]
    public function user_policy_allows_personal_data_viewing_for_self()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'support_agent')->first();
        $user->assignRole($role);

        $this->assertTrue($user->can('viewPersonalData', $user));
    }

    #[Test]
    public function user_policy_allows_personal_data_viewing_for_compliance_auditor()
    {
        $auditor = User::factory()->create();
        $targetUser = User::factory()->create();

        $role = Role::where('name', 'compliance_auditor')->first();
        $auditor->assignRole($role);

        $this->assertTrue($auditor->can('viewPersonalData', $targetUser));
    }

    #[Test]
    public function user_policy_allows_global_personal_data_viewing_with_permission()
    {
        $admin = User::factory()->create();
        $targetUser = User::factory()->create();

        $permission = Permission::create([
            'name' => 'users.view_personal_data_all',
            'resource' => 'users',
            'action' => 'view_personal_data_all',
        ]);

        $role = Role::where('name', 'system_administrator')->first();
        $role->givePermissionTo($permission);
        $admin->assignRole($role);

        $this->assertTrue($admin->can('viewPersonalData', $targetUser));
    }

    #[Test]
    public function user_policy_prevents_self_impersonation()
    {
        $user = User::factory()->create();

        $permission = Permission::where('name', 'users.impersonate')->first();
        $role = Role::where('name', 'system_administrator')->first();
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $this->assertFalse($user->can('impersonate', $user));
    }

    #[Test]
    public function user_policy_allows_update_own_profile()
    {
        $user = User::factory()->create();

        $permission = Permission::create([
            'name' => 'users.edit_own',
            'resource' => 'users',
            'action' => 'edit_own',
        ]);

        $role = Role::where('name', 'support_agent')->first();
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $this->assertTrue($user->can('update', $user));
    }

    #[Test]
    public function user_policy_allows_department_scoped_updating()
    {
        $department = Department::where('name', 'IT Support')->first();
        $manager = User::factory()->create(['department_id' => $department->id]);
        $subordinate = User::factory()->create(['department_id' => $department->id]);

        $permission = Permission::create([
            'name' => 'users.edit_department',
            'resource' => 'users',
            'action' => 'edit_department',
        ]);

        $role = Role::where('name', 'department_manager')->first();
        $role->givePermissionTo($permission);
        $manager->assignRole($role);

        $this->assertTrue($manager->can('update', $subordinate));
    }

    #[Test]
    public function user_policy_allows_department_scoped_deletion()
    {
        $department = Department::where('name', 'IT Support')->first();
        $manager = User::factory()->create(['department_id' => $department->id]);
        $subordinate = User::factory()->create(['department_id' => $department->id]);

        $permission = Permission::create([
            'name' => 'users.delete_department',
            'resource' => 'users',
            'action' => 'delete_department',
        ]);

        $role = Role::where('name', 'department_manager')->first();
        $role->givePermissionTo($permission);
        $manager->assignRole($role);

        $this->assertTrue($manager->can('delete', $subordinate));
    }

    #[Test]
    public function user_policy_allows_user_creation_with_permission()
    {
        $user = User::factory()->create();

        $permission = Permission::create([
            'name' => 'users.create',
            'resource' => 'users',
            'action' => 'create',
        ]);

        $role = Role::where('name', 'department_manager')->first();
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $this->assertTrue($user->can('create', User::class));
    }

    #[Test]
    public function user_policy_allows_user_restoration_with_permission()
    {
        $user = User::factory()->create();
        $deletedUser = User::factory()->create();

        $permission = Permission::create([
            'name' => 'users.restore',
            'resource' => 'users',
            'action' => 'restore',
        ]);

        $role = Role::where('name', 'system_administrator')->first();
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $this->assertTrue($user->can('restore', $deletedUser));
    }

    #[Test]
    public function role_policy_allows_view_with_global_permission()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'support_agent')->first();

        $permission = Permission::create([
            'name' => 'roles.view_all',
            'resource' => 'roles',
            'action' => 'view_all',
        ]);

        $adminRole = Role::where('name', 'system_administrator')->first();
        $adminRole->givePermissionTo($permission);
        $user->assignRole($adminRole);

        $this->assertTrue($user->can('view', $role));
    }

    #[Test]
    public function role_policy_allows_view_with_department_permission_for_lower_hierarchy()
    {
        $user = User::factory()->create();
        $lowerRole = Role::where('name', 'support_agent')->first(); // hierarchy_level = 1
        $higherRole = Role::where('name', 'department_manager')->first(); // hierarchy_level = 2

        $permission = Permission::create([
            'name' => 'roles.view_department',
            'resource' => 'roles',
            'action' => 'view_department',
        ]);

        $higherRole->givePermissionTo($permission);
        $user->assignRole($higherRole);

        $this->assertTrue($user->can('view', $lowerRole));
        $this->assertTrue($user->can('view', $higherRole)); // Same level should be allowed
    }

    #[Test]
    public function role_policy_prevents_view_of_higher_hierarchy_roles()
    {
        $user = User::factory()->create();
        $lowerRole = Role::where('name', 'support_agent')->first(); // hierarchy_level = 1
        $higherRole = Role::where('name', 'system_administrator')->first(); // hierarchy_level = 4

        $permission = Permission::create([
            'name' => 'roles.view_department',
            'resource' => 'roles',
            'action' => 'view_department',
        ]);

        $lowerRole->givePermissionTo($permission);
        $user->assignRole($lowerRole);

        $this->assertFalse($user->can('view', $higherRole));
    }

    #[Test]
    public function role_policy_allows_creation_with_permission()
    {
        $user = User::factory()->create();

        $permission = Permission::create([
            'name' => 'roles.create',
            'resource' => 'roles',
            'action' => 'create',
        ]);

        $role = Role::where('name', 'system_administrator')->first();
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $this->assertTrue($user->can('create', Role::class));
    }

    #[Test]
    public function role_policy_allows_deletion_of_lower_hierarchy_roles()
    {
        $user = User::factory()->create();
        $lowerRole = Role::where('name', 'support_agent')->first(); // hierarchy_level = 1
        $higherRole = Role::where('name', 'system_administrator')->first(); // hierarchy_level = 4

        $permission = Permission::create([
            'name' => 'roles.delete',
            'resource' => 'roles',
            'action' => 'delete',
        ]);

        $higherRole->givePermissionTo($permission);
        $user->assignRole($higherRole);

        $this->assertTrue($user->can('delete', $lowerRole));
    }

    #[Test]
    public function role_policy_prevents_deletion_of_equal_or_higher_hierarchy()
    {
        $user = User::factory()->create();
        $sameRole = Role::where('name', 'system_administrator')->first(); // hierarchy_level = 4
        $permission = Permission::create([
            'name' => 'roles.delete',
            'resource' => 'roles',
            'action' => 'delete',
        ]);

        $sameRole->givePermissionTo($permission);
        $user->assignRole($sameRole);

        // Should not be able to delete role of same hierarchy level
        $this->assertFalse($user->can('delete', $sameRole));
    }

    #[Test]
    public function role_policy_allows_revoke_with_permission()
    {
        $user = User::factory()->create();
        $lowerRole = Role::where('name', 'support_agent')->first(); // hierarchy_level = 1

        $permission = Permission::create([
            'name' => 'roles.revoke_all',
            'resource' => 'roles',
            'action' => 'revoke_all',
        ]);

        $higherRole = Role::where('name', 'system_administrator')->first();
        $higherRole->givePermissionTo($permission);
        $user->assignRole($higherRole);

        $this->assertTrue($user->can('revoke', $lowerRole));
    }

    #[Test]
    public function role_policy_allows_permission_management_for_lower_hierarchy()
    {
        $user = User::factory()->create();
        $lowerRole = Role::where('name', 'support_agent')->first(); // hierarchy_level = 1

        $permission = Permission::create([
            'name' => 'roles.manage_permissions',
            'resource' => 'roles',
            'action' => 'manage_permissions',
        ]);

        $higherRole = Role::where('name', 'system_administrator')->first();
        $higherRole->givePermissionTo($permission);
        $user->assignRole($higherRole);

        $this->assertTrue($user->can('managePermissions', $lowerRole));
    }

    #[Test]
    public function department_policy_allows_view_any_with_permission()
    {
        $user = User::factory()->create();

        $permission = Permission::create([
            'name' => 'departments.view_own',
            'resource' => 'departments',
            'action' => 'view_own',
        ]);

        $role = Role::where('name', 'support_agent')->first();
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $this->assertTrue($user->can('viewAny', Department::class));
    }

    #[Test]
    public function department_policy_allows_update_own_department()
    {
        $department = Department::where('name', 'IT Support')->first();
        $manager = User::factory()->create(['department_id' => $department->id]);

        $permission = Permission::create([
            'name' => 'departments.update_own',
            'resource' => 'departments',
            'action' => 'update_own',
        ]);

        $role = Role::where('name', 'department_manager')->first();
        $role->givePermissionTo($permission);
        $manager->assignRole($role);

        $this->assertTrue($manager->can('update', $department));
    }

    #[Test]
    public function department_policy_allows_creation_with_permission()
    {
        $user = User::factory()->create();

        $permission = Permission::create([
            'name' => 'departments.create',
            'resource' => 'departments',
            'action' => 'create',
        ]);

        $role = Role::where('name', 'system_administrator')->first();
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $this->assertTrue($user->can('create', Department::class));
    }

    #[Test]
    public function department_policy_allows_analytics_viewing_for_own_department()
    {
        $department = Department::where('name', 'IT Support')->first();
        $manager = User::factory()->create(['department_id' => $department->id]);

        $permission = Permission::create([
            'name' => 'analytics.view_department',
            'resource' => 'analytics',
            'action' => 'view_department',
        ]);

        $role = Role::where('name', 'department_manager')->first();
        $role->givePermissionTo($permission);
        $manager->assignRole($role);

        $this->assertTrue($manager->can('viewAnalytics', $department));
    }

    #[Test]
    public function department_policy_allows_manager_assignment_with_permission()
    {
        $department = Department::where('name', 'IT Support')->first();
        $admin = User::factory()->create();

        $permission = Permission::create([
            'name' => 'departments.assign_manager',
            'resource' => 'departments',
            'action' => 'assign_manager',
        ]);

        $role = Role::where('name', 'system_administrator')->first();
        $role->givePermissionTo($permission);
        $admin->assignRole($role);

        $this->assertTrue($admin->can('assignManager', $department));
    }

    #[Test]
    public function department_policy_prevents_deletion_with_child_departments()
    {
        // Create parent and child departments
        $parentDept = Department::create([
            'name' => 'Parent Department',
            'path' => '/4',
        ]);

        $childDept = Department::create([
            'name' => 'Child Department',
            'path' => '/4/1',
            'parent_id' => $parentDept->id,
        ]);

        $admin = User::factory()->create();

        $permission = Permission::create([
            'name' => 'departments.delete',
            'resource' => 'departments',
            'action' => 'delete',
        ]);

        $role = Role::where('name', 'system_administrator')->first();
        $role->givePermissionTo($permission);
        $admin->assignRole($role);

        // Should not be able to delete department with child departments
        $this->assertFalse($admin->can('delete', $parentDept));

        // Should be able to delete child department (no children, no users)
        $this->assertTrue($admin->can('delete', $childDept));
    }

    #[Test]
    public function user_policy_denies_access_without_any_permission()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $role = Role::where('name', 'support_agent')->first();
        $user1->assignRole($role);

        // Without any relevant permissions, should not be able to view other users
        $this->assertFalse($user1->can('view', $user2));
        $this->assertFalse($user1->can('update', $user2));
        $this->assertFalse($user1->can('delete', $user2));
    }

    #[Test]
    public function role_policy_respects_department_scoped_assignment()
    {
        $department = Department::where('name', 'IT Support')->first();
        $manager = User::factory()->create(['department_id' => $department->id]);
        $targetUser = User::factory()->create(['department_id' => $department->id]);
        $otherUser = User::factory()->create(); // Different department

        $permission = Permission::create([
            'name' => 'roles.assign_department',
            'resource' => 'roles',
            'action' => 'assign_department',
        ]);

        $role = Role::where('name', 'department_manager')->first();
        $role->givePermissionTo($permission);
        $manager->assignRole($role);

        $lowerRole = Role::where('name', 'support_agent')->first();

        // Should be able to assign role within department but not with higher hierarchy
        $this->assertTrue($manager->can('assign', $lowerRole));
    }

    #[Test]
    public function department_policy_allows_view_for_child_departments()
    {
        // Create parent and child departments
        $parentDept = Department::create([
            'name' => 'Parent IT',
            'path' => '/5',
        ]);

        $childDept = Department::create([
            'name' => 'Child IT Support',
            'path' => '/5/1',
            'parent_id' => $parentDept->id,
        ]);

        $manager = User::factory()->create(['department_id' => $parentDept->id]);

        $role = Role::where('name', 'department_manager')->first();
        $manager->assignRole($role);

        // Department managers should be able to view child departments
        $this->assertTrue($manager->can('view', $childDept));
    }

    #[Test]
    public function user_policy_prevents_cross_department_operations_without_global_permission()
    {
        $dept1 = Department::where('name', 'IT Support')->first();
        $dept2 = Department::where('name', 'Sales')->first();

        $user1 = User::factory()->create(['department_id' => $dept1->id]);
        $user2 = User::factory()->create(['department_id' => $dept2->id]);

        $permission = Permission::create([
            'name' => 'users.edit_department',
            'resource' => 'users',
            'action' => 'edit_department',
        ]);

        $role = Role::where('name', 'department_manager')->first();
        $role->givePermissionTo($permission);
        $user1->assignRole($role);

        // Should not be able to edit users from different departments
        $this->assertFalse($user1->can('update', $user2));
        $this->assertFalse($user1->can('delete', $user2));
    }

    #[Test]
    public function role_policy_handles_users_without_roles_gracefully()
    {
        $userWithoutRole = User::factory()->create();
        $targetRole = Role::where('name', 'support_agent')->first();

        // Users without roles should not be able to perform role operations
        $this->assertFalse($userWithoutRole->can('view', $targetRole));
        $this->assertFalse($userWithoutRole->can('update', $targetRole));
        $this->assertFalse($userWithoutRole->can('assign', $targetRole));
    }

    #[Test]
    public function department_policy_allows_global_analytics_access()
    {
        $department = Department::where('name', 'IT Support')->first();
        $admin = User::factory()->create();

        $permission = Permission::create([
            'name' => 'analytics.view_all',
            'resource' => 'analytics',
            'action' => 'view_all',
        ]);

        $role = Role::where('name', 'system_administrator')->first();
        $role->givePermissionTo($permission);
        $admin->assignRole($role);

        $this->assertTrue($admin->can('viewAnalytics', $department));
    }

    #[Test]
    public function user_policy_allows_force_delete_with_permission()
    {
        $admin = User::factory()->create();
        $targetUser = User::factory()->create();

        $permission = Permission::create([
            'name' => 'users.force_delete',
            'resource' => 'users',
            'action' => 'force_delete',
        ]);

        $role = Role::where('name', 'system_administrator')->first();
        $role->givePermissionTo($permission);
        $admin->assignRole($role);

        $this->assertTrue($admin->can('forceDelete', $targetUser));
    }

    #[Test]
    public function role_policy_allows_force_delete_with_permission()
    {
        $admin = User::factory()->create();
        $targetRole = Role::where('name', 'support_agent')->first();

        $permission = Permission::create([
            'name' => 'roles.force_delete',
            'resource' => 'roles',
            'action' => 'force_delete',
        ]);

        $role = Role::where('name', 'system_administrator')->first();
        $role->givePermissionTo($permission);
        $admin->assignRole($role);

        $this->assertTrue($admin->can('forceDelete', $targetRole));
    }

    #[Test]
    public function department_policy_allows_force_delete_with_permission()
    {
        $admin = User::factory()->create();
        $department = Department::where('name', 'Marketing')->first();

        $permission = Permission::create([
            'name' => 'departments.force_delete',
            'resource' => 'departments',
            'action' => 'force_delete',
        ]);

        $role = Role::where('name', 'system_administrator')->first();
        $role->givePermissionTo($permission);
        $admin->assignRole($role);

        $this->assertTrue($admin->can('forceDelete', $department));
    }

    #[Test]
    public function user_policy_supports_view_any_functionality()
    {
        $user = User::factory()->create();

        $permission = Permission::create([
            'name' => 'users.view_all',
            'resource' => 'users',
            'action' => 'view_all',
        ]);

        $role = Role::where('name', 'system_administrator')->first();
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $this->assertTrue($user->can('viewAny', User::class));
    }

    #[Test]
    public function role_policy_supports_view_any_functionality()
    {
        $user = User::factory()->create();

        $permission = Permission::create([
            'name' => 'roles.view_all',
            'resource' => 'roles',
            'action' => 'view_all',
        ]);

        $role = Role::where('name', 'system_administrator')->first();
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $this->assertTrue($user->can('viewAny', Role::class));
    }

    protected function createBasicRoles(): void
    {
        $roles = [
            ['name' => 'support_agent', 'hierarchy_level' => 1],
            ['name' => 'department_manager', 'hierarchy_level' => 2],
            ['name' => 'regional_manager', 'hierarchy_level' => 3],
            ['name' => 'system_administrator', 'hierarchy_level' => 4],
            ['name' => 'compliance_auditor', 'hierarchy_level' => 2],
            ['name' => 'knowledge_curator', 'hierarchy_level' => 1],
        ];

        foreach ($roles as $roleData) {
            Role::create($roleData);
        }
    }

    protected function createBasicPermissions(): void
    {
        $permissions = [
            ['name' => 'users.view_department', 'resource' => 'users', 'action' => 'view_department'],
            ['name' => 'users.impersonate', 'resource' => 'users', 'action' => 'impersonate'],
        ];

        foreach ($permissions as $permissionData) {
            Permission::create($permissionData);
        }
    }

    protected function createTestDepartments(): void
    {
        $departments = [
            ['name' => 'IT Support', 'path' => '/1'],
            ['name' => 'Sales', 'path' => '/2'],
            ['name' => 'Marketing', 'path' => '/3'],
        ];

        foreach ($departments as $deptData) {
            Department::create($deptData);
        }
    }
}
