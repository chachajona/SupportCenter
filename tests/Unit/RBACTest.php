<?php

namespace Tests\Unit;

use App\Models\EmergencyAccess;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\EmergencyAccessService;
use App\Services\TemporalAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RBACTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create basic test data
        $this->createBasicRoles();
        $this->createBasicPermissions();
    }

    #[Test]
    public function user_can_be_assigned_role()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'support_agent')->first();

        $user->assignRole($role);

        $this->assertTrue($user->hasRole('support_agent'));
        $this->assertDatabaseHas('role_user', [
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);
    }

    #[Test]
    public function user_can_be_assigned_multiple_roles()
    {
        $user = User::factory()->create();
        $agentRole = Role::where('name', 'support_agent')->first();
        $curatorRole = Role::where('name', 'knowledge_curator')->first();

        $user->assignRole([$agentRole, $curatorRole]);

        $this->assertTrue($user->hasRole('support_agent'));
        $this->assertTrue($user->hasRole('knowledge_curator'));
        $this->assertEquals(2, $user->roles()->count());
    }

    #[Test]
    public function role_can_have_permissions()
    {
        $role = Role::where('name', 'support_agent')->first();
        $permission = Permission::where('name', 'tickets.create')->first();

        $role->givePermissionTo($permission);

        $this->assertTrue($role->hasPermissionTo('tickets.create'));
    }

    #[Test]
    public function user_inherits_permissions_from_role()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'support_agent')->first();
        $permission = Permission::where('name', 'tickets.create')->first();

        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $this->assertTrue($user->hasPermissionTo('tickets.create'));
        $this->assertTrue($user->can('tickets.create'));
    }

    #[Test]
    public function role_hierarchy_works_correctly()
    {
        $supportAgent = Role::where('name', 'support_agent')->first();
        $manager = Role::where('name', 'department_manager')->first();

        // Manager should have higher hierarchy level
        $this->assertGreaterThan($supportAgent->hierarchy_level, $manager->hierarchy_level);
    }

    #[Test]
    public function temporal_permissions_can_be_granted()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'system_administrator')->first();
        $grantedBy = User::factory()->create();

        // Give the granter permission to grant temporary roles
        $managerRole = Role::where('name', 'department_manager')->first();
        $grantedBy->assignRole($managerRole);

        $service = app(TemporalAccessService::class);
        $result = $service->grantTemporaryRole(
            $user->id,
            $role->id,
            60, // 60 minutes
            'Emergency maintenance',
            $grantedBy->id
        );

        $this->assertTrue($result);
        $this->assertDatabaseHas('role_user', [
            'user_id' => $user->id,
            'role_id' => $role->id,
            'granted_by' => $grantedBy->id,
        ]);
    }

    #[Test]
    public function temporal_permissions_expire_correctly()
    {
        Carbon::setTestNow(now());

        $user = User::factory()->create();
        $role = Role::where('name', 'support_agent')->first();

        // Grant role that expires in the past
        $user->roles()->attach($role->id, [
            'expires_at' => now()->subMinute(),
            'is_active' => true,
            'granted_at' => now()->subHour(),
        ]);

        // The role has already expired (expires_at in the past) so the user should NOT have it.
        $this->assertFalse($user->hasRole($role->name));

        Carbon::setTestNow(); // Reset time
    }

    #[Test]
    public function emergency_access_can_be_granted()
    {
        $user = User::factory()->create();
        $grantedBy = User::factory()->create();
        $permissions = ['tickets.delete_all', 'system.maintenance'];

        // Give the granter appropriate role
        $adminRole = Role::where('name', 'system_administrator')->first();
        $grantedBy->assignRole($adminRole);

        $this->actingAs($grantedBy);

        $service = app(EmergencyAccessService::class);
        $emergencyAccess = $service->grantEmergencyAccess(
            $user->id,
            $permissions,
            'Critical system maintenance',
            30 // 30 minutes
        );

        $this->assertInstanceOf(EmergencyAccess::class, $emergencyAccess);
        $this->assertEquals($user->id, $emergencyAccess->user_id);
        $this->assertEquals($permissions, $emergencyAccess->permissions);
        $this->assertTrue($emergencyAccess->is_active);
    }

    #[Test]
    public function inactive_roles_are_not_effective()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'support_agent')->first();
        $permission = Permission::where('name', 'tickets.create')->first();

        $role->givePermissionTo($permission);
        $user->assignRole($role);

        // Verify user has permission initially
        $this->assertTrue($user->hasPermissionTo('tickets.create'));

        // Deactivate the role
        $role->update(['is_active' => false]);

        // Clear cache and refresh user
        app(\App\Services\PermissionCacheService::class)->clearUserCache($user->id);
        $user->refresh();
        $user->load('roles');

        // User should not have permission from inactive role
        $this->assertFalse($user->hasPermissionTo('tickets.create'));
    }

    #[Test]
    public function inactive_permissions_are_not_effective()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'support_agent')->first();
        $permission = Permission::where('name', 'tickets.create')->first();

        $role->givePermissionTo($permission);
        $user->assignRole($role);

        // Verify user has permission initially
        $this->assertTrue($user->hasPermissionTo('tickets.create'));

        // Deactivate the permission
        $permission->update(['is_active' => false]);

        // Clear cache and refresh user
        app(\App\Services\PermissionCacheService::class)->clearUserCache($user->id);
        $user->refresh();
        $user->load('roles.permissions');

        // User should not have inactive permission
        $this->assertFalse($user->hasPermissionTo('tickets.create'));
    }

    #[Test]
    public function user_cannot_assign_role_to_themselves()
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can('assignRole', $user));
    }

    #[Test]
    public function role_hierarchy_prevents_escalation()
    {
        $lowUser = User::factory()->create();
        $highUser = User::factory()->create();

        $lowRole = Role::where('name', 'support_agent')->first();
        $highRole = Role::where('name', 'system_administrator')->first();
        $impersonatePermission = Permission::where('name', 'users.impersonate')->first();

        // Give the high role the impersonate permission
        $highRole->givePermissionTo($impersonatePermission);

        $lowUser->assignRole($lowRole);
        $highUser->assignRole($highRole);

        // Low hierarchy user cannot impersonate high hierarchy user
        $this->assertFalse($lowUser->can('impersonate', $highUser));
        // High hierarchy user can impersonate low hierarchy user
        $this->assertTrue($highUser->can('impersonate', $lowUser));
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
            ['name' => 'tickets.create', 'resource' => 'tickets', 'action' => 'create'],
            ['name' => 'tickets.view_own', 'resource' => 'tickets', 'action' => 'view_own'],
            ['name' => 'tickets.view_department', 'resource' => 'tickets', 'action' => 'view_department'],
            ['name' => 'tickets.view_all', 'resource' => 'tickets', 'action' => 'view_all'],
            ['name' => 'users.view_department', 'resource' => 'users', 'action' => 'view_department'],
            ['name' => 'users.impersonate', 'resource' => 'users', 'action' => 'impersonate'],
        ];

        foreach ($permissions as $permissionData) {
            Permission::create($permissionData);
        }
    }
}
