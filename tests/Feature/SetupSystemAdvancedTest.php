<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SetupStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SetupSystemAdvancedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');
        if (file_exists(storage_path('app/setup.lock'))) {
            unlink(storage_path('app/setup.lock'));
        }
        SetupStatus::query()->delete();
    }

    protected function tearDown(): void
    {
        if (file_exists(storage_path('app/setup.lock'))) {
            unlink(storage_path('app/setup.lock'));
        }
        parent::tearDown();
    }

    #[Test]
    public function admin_creation_with_enhanced_validation(): void
    {
        $this->seed(RolePermissionSeeder::class);

        // Test for validation errors (e.g., short password)
        $response = $this->postJson('/setup/admin', [
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => 'short',
            'password_confirmation' => 'short',
            'helpdesk_name' => 'Test Helpdesk',
            'helpdesk_url' => 'https://test.com',
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['password']);

        // Test successful creation
        $response = $this->postJson('/setup/admin', [
            'name' => 'System Administrator',
            'email' => 'admin@osticket.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'helpdesk_name' => 'Acme Support',
            'helpdesk_url' => 'http://localhost',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertTrue(SetupStatus::isCompleted('admin_created'));
        $admin = User::where('email', 'admin@osticket.com')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('system_administrator'));
    }

    #[Test]
    public function duplicate_admin_creation_prevention(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->postJson('/setup/admin', [
            'name' => 'First Admin',
            'email' => 'admin1@test.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'helpdesk_name' => 'Acme Support',
            'helpdesk_url' => 'http://localhost',
        ]);

        // Second attempt to create an admin
        $response = $this->postJson('/setup/admin', [
            'name' => 'Second Admin',
            'email' => 'admin2@test.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'helpdesk_name' => 'Acme Support',
            'helpdesk_url' => 'http://localhost',
        ]);

        $response->assertStatus(422)->assertJsonFragment(['message' => 'Administrator has already been created.']);
    }

    #[Test]
    public function comprehensive_role_inheritance(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $expectedRoles = ['support_agent', 'department_manager', 'regional_manager', 'system_administrator', 'compliance_auditor', 'knowledge_curator'];
        foreach ($expectedRoles as $roleName) {
            $this->assertDatabaseHas('roles', ['name' => $roleName]);
        }

        $departmentManager = Role::with('permissions')->where('name', 'department_manager')->first();
        $supportAgent = Role::with('permissions')->where('name', 'support_agent')->first();

        if ($departmentManager && $supportAgent) {
            $supportAgentPermissions = $supportAgent->permissions->pluck('name');
            $this->assertEmpty($supportAgentPermissions->diff($departmentManager->permissions->pluck('name')));
        }
    }

    #[Test]
    public function system_administrator_has_all_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $systemAdmin = Role::where('name', 'system_administrator')->first();
        $allPermissionsCount = Permission::count();
        $this->assertEquals($allPermissionsCount, $systemAdmin->permissions()->count());
    }

    #[Test]
    public function compliance_auditor_read_only_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $complianceAuditor = Role::where('name', 'compliance_auditor')->first();
        $auditorPermissions = $complianceAuditor->permissions->pluck('name');

        $this->assertTrue($auditorPermissions->contains('tickets.view_all'));
        $this->assertFalse($auditorPermissions->contains('tickets.create'));
    }

    #[Test]
    public function knowledge_curator_specific_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $knowledgeCurator = Role::where('name', 'knowledge_curator')->first();
        $curatorPermissions = $knowledgeCurator->permissions->pluck('name');

        $this->assertTrue($curatorPermissions->contains('knowledge.create_articles'));
        $this->assertTrue($curatorPermissions->contains('knowledge.approve_articles'));
        $this->assertFalse($curatorPermissions->contains('system.configuration'));
    }

    #[Test]
    public function setup_cleanup_command(): void
    {
        // To test the cleanup command, the setup must be fully complete.
        $this->seed(RolePermissionSeeder::class);
        // Mark all core steps as completed to satisfy the stricter validation
        // in CleanupSetupCommand (database_migrated, roles_seeded, admin_created)
        SetupStatus::markCompleted('database_migrated');
        SetupStatus::markCompleted('roles_seeded');
        SetupStatus::markCompleted('admin_created');
        SetupStatus::markCompleted('setup_completed');

        $this->artisan('setup:cleanup --force')
            ->expectsOutput('Setup system cleaned up successfully.')
            ->assertExitCode(0);

        $this->assertFileExists(storage_path('app/setup.lock'));
        unlink(storage_path('app/setup.lock'));
    }

    #[Test]
    public function setup_cleanup_command_fails_when_not_completed(): void
    {
        // Do not complete setup for this test.
        $this->artisan('setup:cleanup --force')
            ->expectsOutput('Setup is not completed. Cannot cleanup.')
            ->assertExitCode(1);
        $this->assertFileDoesNotExist(storage_path('app/setup.lock'));
    }

    #[Test]
    public function rate_limiting_on_setup_endpoints(): void
    {
        $this->markTestIncomplete('Rate limiting tests need to be implemented.');
    }

    #[Test]
    public function setup_form_data_normalization(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $response = $this->postJson('/setup/admin', [
            'name' => '  Test Admin  ',
            'email' => '  ADMIN@TEST.COM  ',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'helpdesk_name' => '  Test Helpdesk  ',
            'helpdesk_url' => 'https://test.com',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        // Fetch user by normalized email and assert name is trimmed.
        $admin = User::where('email', 'admin@test.com')->first();
        $this->assertNotNull($admin);
        $this->assertEquals('Test Admin', $admin->name);
    }

    #[Test]
    public function setup_completion_audit_logging(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with(\Mockery::pattern('/setup completed successfully/i'));

        // Complete all previous steps before hitting the final one
        SetupStatus::markCompleted('database_migrated');
        SetupStatus::markCompleted('roles_seeded');
        SetupStatus::markCompleted('admin_created');

        $this->post('/setup/complete');
    }
}
