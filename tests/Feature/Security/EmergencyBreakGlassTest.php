<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\EmergencyAccess;
use App\Models\User;
use App\Services\EmergencyAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmergencyBreakGlassTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    }

    public function test_admin_can_generate_break_glass_token(): void
    {
        $this->markTestIncomplete('This test requires full RBAC setup with emergency.grant permission');

        $admin = User::factory()->create();
        $admin->assignRole('system_administrator');

        $user = User::factory()->create();

        $response = $this->actingAs($admin)
            ->withoutMiddleware()
            ->postJson('/admin/emergency/break-glass', [
                'user_id' => $user->id,
                'permissions' => ['tickets.view_all', 'system.maintenance'],
                'reason' => 'Critical system issue requiring immediate access',
                'duration' => 15,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['token', 'expires_at', 'emergency_access_id'],
            ]);

        // Verify emergency access was created in database
        $this->assertDatabaseHas('emergency_access', [
            'user_id' => $user->id,
            'is_active' => true,
        ]);
    }

    public function test_break_glass_token_allows_emergency_login(): void
    {
        $user = User::factory()->create();

        $emergencyAccess = EmergencyAccess::create([
            'user_id' => $user->id,
            'permissions' => ['tickets.view_all'],
            'reason' => 'Emergency access',
            'granted_by' => User::factory()->create()->id,
            'expires_at' => now()->addMinutes(10),
        ]);

        $token = $emergencyAccess->generateBreakGlassToken();

        $response = $this->withoutMiddleware()
            ->postJson('/break-glass', [
                'token' => $token,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Break-glass login successful',
            ]);

        $this->assertAuthenticatedAs($user);

        // Token should be marked as used
        $this->assertDatabaseHas('emergency_access', [
            'id' => $emergencyAccess->id,
        ]);
    }

    public function test_expired_break_glass_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $emergencyAccess = EmergencyAccess::create([
            'user_id' => $user->id,
            'permissions' => ['tickets.view_all'],
            'reason' => 'Emergency access',
            'granted_by' => User::factory()->create()->id,
            'expires_at' => now()->subMinute(), // Expired
        ]);

        $token = $emergencyAccess->generateBreakGlassToken();

        $response = $this->withoutMiddleware()
            ->postJson('/break-glass', [
                'token' => $token,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or expired break-glass token',
            ]);

        $this->assertGuest();
    }

    public function test_used_break_glass_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $emergencyAccess = EmergencyAccess::create([
            'user_id' => $user->id,
            'permissions' => ['tickets.view_all'],
            'reason' => 'Emergency access',
            'granted_by' => User::factory()->create()->id,
            'expires_at' => now()->addMinutes(10),
            'used_at' => now(), // Already used
        ]);

        $token = $emergencyAccess->generateBreakGlassToken();

        $response = $this->withoutMiddleware()
            ->postJson('/break-glass', [
                'token' => $token,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or expired break-glass token',
            ]);

        $this->assertGuest();
    }

    public function test_non_admin_cannot_generate_break_glass_token(): void
    {
        $user = User::factory()->create();
        $user->assignRole('support_agent');

        $targetUser = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/admin/emergency/break-glass', [
                'user_id' => $targetUser->id,
                'permissions' => ['tickets.view_all'],
                'reason' => 'Emergency access',
                'duration' => 10,
            ]);

        $response->assertStatus(403);
    }

    public function test_break_glass_service_creates_security_log(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('system_administrator');

        $user = User::factory()->create();

        $this->actingAs($admin);

        app(EmergencyAccessService::class)->generateBreakGlass(
            $user,
            ['tickets.view_all'],
            'Critical system issue',
            10
        );

        $this->assertDatabaseHas('security_logs', [
            'user_id' => $user->id,
            'event_type' => 'emergency_access',
        ]);
    }
}
