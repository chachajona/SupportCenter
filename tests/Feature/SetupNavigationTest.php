<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SetupStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SetupNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure we're in a clean setup state for testing
        $this->resetSetupForTesting();
    }

    #[Test]
    public function can_navigate_to_completed_steps(): void
    {
        // Complete prerequisites
        SetupStatus::markCompleted('prerequisites_checked');

        // Should be able to access prerequisites page
        $response = $this->withoutMiddleware(['prevent.setup.access'])->get('/setup/prerequisites');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('setup/index'));
    }

    #[Test]
    public function cannot_navigate_to_incomplete_steps(): void
    {
        // Try to access database step without completing prerequisites
        $response = $this->withoutMiddleware(['prevent.setup.access'])->get('/setup/database');
        $response->assertRedirect('/setup');
    }

    #[Test]
    public function migration_api_prevents_duplicate_execution(): void
    {
        // Mark as already completed
        SetupStatus::markCompleted('database_migrated');

        $response = $this->withoutMiddleware()->post('/setup/migrate');

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Database migrations already completed.',
        ]);
    }

    #[Test]
    public function seeding_api_prevents_duplicate_execution(): void
    {
        // Mark as already completed
        SetupStatus::markCompleted('roles_seeded');

        $response = $this->withoutMiddleware()->post('/setup/seed');

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Roles and permissions have already been seeded.',
        ]);
    }

    #[Test]
    public function admin_creation_prevents_duplicate_execution(): void
    {
        // Mark as already completed
        SetupStatus::markCompleted('admin_created');

        $response = $this->withoutMiddleware()->postJson('/setup/admin', [
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'helpdesk_name' => 'Test Helpdesk',
            'helpdesk_url' => 'https://test.com',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Administrator has already been created.']);
    }

    #[Test]
    public function database_configuration_prevents_reconfiguration(): void
    {
        // Mark database as configured
        SetupStatus::markCompleted('database_configured');
        SetupStatus::markCompleted('database_migrated');

        $response = $this->withoutMiddleware()->postJson('/setup/database', [
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => ':memory:',
            'username' => 'test_user',
            'password' => '',
        ]);

        // Should be forbidden if already configured.
        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Database is already configured. Reconfiguration is not allowed.',
        ]);
    }

    #[Test]
    public function step_validation_prevents_skipping(): void
    {
        // Try to access roles step without completing database migration
        $response = $this->withoutMiddleware(['prevent.setup.access'])->get('/setup/roles-seeded');
        $response->assertRedirect('/setup');

        // Try to access app settings without completing roles
        $response = $this->withoutMiddleware(['prevent.setup.access'])->get('/setup/app-settings');
        $response->assertRedirect('/setup');
    }

    #[Test]
    public function navigation_flow_with_completed_steps(): void
    {
        // Complete first two steps
        SetupStatus::markCompleted('prerequisites_checked');
        SetupStatus::markCompleted('database_configured');
        SetupStatus::markCompleted('database_migrated');

        // Should be able to navigate to database step
        $response = $this->withoutMiddleware(['prevent.setup.access'])->get('/setup/database');
        $response->assertStatus(200);

        // Should be able to navigate to roles step
        $response = $this->withoutMiddleware(['prevent.setup.access'])->get('/setup/roles-seeded');
        $response->assertStatus(200);

        // Should not be able to navigate to app settings (roles not completed)
        $response = $this->withoutMiddleware(['prevent.setup.access'])->get('/setup/app-settings');
        $response->assertRedirect('/setup');
    }

    #[Test]
    public function completed_step_shows_navigation_hints(): void
    {
        SetupStatus::markCompleted('prerequisites_checked');

        $response = $this->withoutMiddleware(['prevent.setup.access'])->get('/setup/prerequisites');
        $response->assertStatus(200);

        // Check that the Inertia response contains the step data
        $response->assertInertia(
            fn ($page) => $page
                ->component('setup/index')
                ->where('currentStep', 'prerequisites')
        );
    }
}
