<?php

namespace Tests\Feature\Auth;

use App\Models\SetupStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure mail to use array driver for testing (prevents actual email sending)
        config(['mail.default' => 'array']);

        // Mark setup as completed for testing
        SetupStatus::markCompleted('database_migration');
        SetupStatus::markCompleted('roles_seeded');
        SetupStatus::markCompleted('admin_created');
        SetupStatus::markCompleted('setup_completed');

        // Create setup lock file to skip middleware checks
        $setupLockFile = storage_path('app/setup.lock');
        $lockData = [
            'completed_at' => now()->toISOString(),
            'completed_by' => 'test_environment',
            'version' => config('app.version', '1.0.0'),
        ];
        file_put_contents($setupLockFile, json_encode($lockData));
    }

    protected function tearDown(): void
    {
        // Clean up setup lock file after test
        $setupLockFile = storage_path('app/setup.lock');
        if (file_exists($setupLockFile)) {
            unlink($setupLockFile);
        }

        parent::tearDown();
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        // Verify the user was created
        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    public function test_registration_requires_valid_data()
    {
        // Test missing name
        $response = $this->post('/register', [
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $response->assertSessionHasErrors('name');

        // Test missing email
        $response = $this->post('/register', [
            'name' => 'Test User',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $response->assertSessionHasErrors('email');

        // Test invalid email
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'invalid-email',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $response->assertSessionHasErrors('email');

        // Test missing password
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password_confirmation' => 'password',
        ]);
        $response->assertSessionHasErrors('password');

        // Test password confirmation mismatch
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'different-password',
        ]);
        $response->assertSessionHasErrors('password');
    }

    public function test_registration_requires_unique_email()
    {
        // Create a user first
        $existingUser = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        // Try to register with the same email
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
