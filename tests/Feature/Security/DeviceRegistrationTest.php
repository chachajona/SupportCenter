<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DeviceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles are seeded for permission/role tests
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

        // Define a temporary route for testing device registration middleware
        Route::middleware([\App\Http\Middleware\DeviceRegistrationMiddleware::class])
            ->get('/device-check', fn() => response()->json(['message' => 'OK']))
            ->name('device.check');
    }

    public function test_blocks_unverified_device(): void
    {
        // Simulate production environment so middleware does not early-return
        $this->app['env'] = 'production';

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/device-check', [
                'HTTP_USER_AGENT' => 'FakeBrowser/1.0',
                'REMOTE_ADDR' => '203.0.113.20',
            ])
            ->assertForbidden()
            ->assertJson(['message' => 'Unrecognized device detected. Please verify this device via the link sent to your email.']);
    }

    public function test_allows_verified_device(): void
    {
        $this->app['env'] = 'production';

        $user = User::factory()->create();

        // Pre-create a verified device record that matches the hash the middleware will create.
        $deviceHash = hash('sha256', 'VerifiedBrowser/2.0|');
        Device::create([
            'user_id' => $user->id,
            'device_hash' => $deviceHash,
            'user_agent' => 'VerifiedBrowser/2.0',
            'ip_address' => '203.0.113.21',
            'verified_at' => now(),
            'last_used_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/device-check', [
                'HTTP_USER_AGENT' => 'VerifiedBrowser/2.0',
                'REMOTE_ADDR' => '203.0.113.21',
            ])
            ->assertOk()
            ->assertJson(['message' => 'OK']);
    }

    public function test_auto_verifies_device_for_system_admin_in_local_env(): void
    {
        $this->app['env'] = 'local';

        $admin = User::factory()->create();
        $admin->assignRole('system_administrator');

        $this->actingAs($admin)
            ->get('/device-check', [
                'HTTP_USER_AGENT' => 'AdminBrowser/99.0',
                'REMOTE_ADDR' => '192.0.2.50',
            ])
            ->assertOk();

        $this->assertDatabaseCount('user_devices', 1);
        $this->assertNotNull(Device::first()->verified_at);
    }
}
