<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\PermissionAudit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable CSRF protection for JSON requests in this test suite
        $this->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

        // Seed permissions and roles
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

        // Mark setup as completed to satisfy SetupMiddleware redirects
        $this->completeSetupForTesting();

        // Create admin user
        $this->admin = User::factory()->create();
        $this->admin->assignRole('system_administrator');

        // Create regular user
        $this->user = User::factory()->create();
        $this->user->assignRole('support_agent');
    }

    public function test_test_admin_can_view_roles_index()
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/roles');

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page->component('admin/roles/index')
                ->has('roles')
                ->has('permissions')
        );
    }

    public function test_test_non_admin_cannot_access_roles()
    {
        $response = $this->actingAs($this->user)
            ->get('/admin/roles');

        $response->assertStatus(403);
    }

    public function test_test_admin_can_create_role()
    {
        $roleData = [
            'name' => 'test_role',
            'display_name' => 'Test Role',
            'description' => 'A test role for testing',
            'hierarchy_level' => 2,
            'permissions' => [1, 2, 3], // Assuming these permission IDs exist
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/admin/roles', $roleData);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Role created successfully']);

        $this->assertDatabaseHas('roles', [
            'name' => 'test_role',
            'display_name' => 'Test Role',
        ]);

        // Check audit log
        $this->assertDatabaseHas('permission_audits', [
            'action' => 'granted',
            'performed_by' => $this->admin->id,
        ]);
    }

    public function test_test_role_creation_requires_valid_data()
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/admin/roles', [
                'name' => '', // Invalid: empty name
                'display_name' => '',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'display_name']);
    }

    public function test_test_role_name_must_be_unique()
    {
        $role = Role::factory()->create(['name' => 'existing_role']);

        $response = $this->actingAs($this->admin)
            ->postJson('/admin/roles', [
                'name' => 'existing_role',
                'display_name' => 'Existing Role',
                'hierarchy_level' => 1,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_test_admin_can_update_role()
    {
        $role = Role::factory()->create([
            'name' => 'test_role',
            'display_name' => 'Original Name',
        ]);

        $updateData = [
            'name' => 'test_role',
            'display_name' => 'Updated Name',
            'description' => 'Updated description',
            'hierarchy_level' => 3,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)
            ->patchJson("/admin/roles/{$role->id}", $updateData);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Role updated successfully']);

        $role->refresh();
        $this->assertEquals('Updated Name', $role->display_name);

        // Check audit log
        // Check audit log
        $this->assertDatabaseHas('permission_audits', [
            'role_id' => $role->id,
            'action' => 'modified',
            'performed_by' => $this->admin->id,
        ]);
    }

    public function test_test_admin_can_view_single_role()
    {
        $role = Role::factory()->create();
        $role->givePermissionTo(Permission::first());

        $response = $this->actingAs($this->admin)
            ->get("/admin/roles/{$role->id}");

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page->component('admin/roles/show')
                ->has('role')
                ->has('recentAudits')
        );
    }

    public function test_test_admin_can_delete_non_system_role()
    {
        $role = Role::factory()->create(['name' => 'deletable_role']);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/admin/roles/{$role->id}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Role deleted successfully']);

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_test_cannot_delete_system_roles()
    {
        $systemRole = Role::where('name', 'system_administrator')->first();

        $response = $this->actingAs($this->admin)
            ->deleteJson("/admin/roles/{$systemRole->id}");

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Cannot delete system roles']);
    }

    public function test_test_cannot_delete_role_with_users()
    {
        $role = Role::factory()->create();
        $user = User::factory()->create();
        $user->assignRole($role);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/admin/roles/{$role->id}");

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Cannot delete role that has assigned users. Please reassign users first.']);
    }

    public function test_admin_can_view_permission_matrix()
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/roles/matrix/view');

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page->component('admin/roles/matrix')
                ->has('roles')
                ->has('permissions')
                ->has('matrix')
        );
    }

    public function test_admin_can_update_permission_matrix()
    {
        $role = Role::first();
        $permission = Permission::first();

        $response = $this->actingAs($this->admin)
            ->patchJson('/admin/roles/matrix/update', [
                'role_id' => $role->id,
                'permission_id' => $permission->id,
                'granted' => true,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Permission matrix updated successfully']);

        $this->assertTrue($role->hasPermissionTo($permission));

        // Check audit log
        $this->assertDatabaseHas('permission_audits', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
            'action' => 'granted',
            'performed_by' => $this->admin->id,
        ]);
    }

    public function test_role_filters_work_correctly()
    {
        Role::factory()->create([
            'name' => 'active_role',
            'display_name' => 'Active Role',
            'is_active' => true,
        ]);

        Role::factory()->create([
            'name' => 'inactive_role',
            'display_name' => 'Inactive Role',
            'is_active' => false,
        ]);

        // Test search filter
        $response = $this->actingAs($this->admin)
            ->get('/admin/roles?search=Active');

        $response->assertStatus(200);

        // Test status filter
        $response = $this->actingAs($this->admin)
            ->get('/admin/roles?status=active');

        $response->assertStatus(200);
    }

    public function test_permission_changes_are_audited()
    {
        $role = Role::factory()->create();
        $permission = Permission::first();

        // Grant permission
        $this->actingAs($this->admin)
            ->patchJson('/admin/roles/matrix/update', [
                'role_id' => $role->id,
                'permission_id' => $permission->id,
                'granted' => true,
            ]);

        // Revoke permission
        $this->actingAs($this->admin)
            ->patchJson('/admin/roles/matrix/update', [
                'role_id' => $role->id,
                'permission_id' => $permission->id,
                'granted' => false,
            ]);

        $auditCount = PermissionAudit::where('role_id', $role->id)
            ->where('permission_id', $permission->id)
            ->count();

        $this->assertEquals(2, $auditCount); // One grant, one revoke
    }
}
