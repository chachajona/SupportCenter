<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Models\WebAuthnCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WebAuthnSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_webauthn_settings_page_is_displayed(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/settings/webauthn');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('settings/web-authn')
                ->has('user')
                ->has('credentials')
        );
    }

    public function test_user_can_enable_webauthn(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'webauthn_enabled' => false,
        ]);

        // Simulate password confirmation
        session(['auth.password_confirmed_at' => time()]);

        $response = $this
            ->actingAs($user)
            ->post('/user/webauthn/enable');

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'WebAuthn enabled successfully.',
        ]);

        $this->assertTrue($user->fresh()->webauthn_enabled);
    }

    public function test_enabling_webauthn_requires_password_confirmation(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'webauthn_enabled' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => null])
            ->post('/user/webauthn/enable');

        $response->assertStatus(423);
    }

    public function test_user_can_disable_webauthn(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'webauthn_enabled' => true,
            'two_factor_confirmed_at' => now(),
        ]);

        // Create a WebAuthn credential
        WebAuthnCredential::factory()->create([
            'authenticatable_id' => $user->id,
            'authenticatable_type' => User::class,
        ]);

        // Simulate password confirmation
        session(['auth.password_confirmed_at' => time()]);

        $response = $this
            ->actingAs($user)
            ->delete('/user/webauthn/disable');

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'WebAuthn disabled successfully.',
        ]);

        $user->refresh();
        $this->assertFalse($user->webauthn_enabled);
        $this->assertEquals('totp', $user->preferred_mfa_method);

        // Check that credentials are disabled
        $this->assertFalse($user->webAuthnCredentials()->whereEnabled()->exists());
    }

    public function test_disabling_webauthn_requires_password_confirmation(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'webauthn_enabled' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => null])
            ->delete('/user/webauthn/disable');

        $response->assertStatus(423);
    }

    public function test_user_can_fetch_webauthn_credentials(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'webauthn_enabled' => true,
        ]);

        $credential = WebAuthnCredential::factory()->create([
            'authenticatable_id' => $user->id,
            'authenticatable_type' => User::class,
            'alias' => 'Test Device',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/user/webauthn/credentials');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment([
            'id' => $credential->id,
            'name' => 'Test Device',
            'type' => 'security-key',
        ]);
    }

    public function test_only_enabled_credentials_are_returned(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'webauthn_enabled' => true,
        ]);

        // Create enabled credential
        $enabledCredential = WebAuthnCredential::factory()->create([
            'authenticatable_id' => $user->id,
            'authenticatable_type' => User::class,
            'alias' => 'Enabled Device',
        ]);

        // Create disabled credential
        WebAuthnCredential::factory()->disabled()->create([
            'authenticatable_id' => $user->id,
            'authenticatable_type' => User::class,
            'alias' => 'Disabled Device',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/user/webauthn/credentials');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment([
            'id' => $enabledCredential->id,
            'name' => 'Enabled Device',
        ]);
    }

    public function test_user_preferred_mfa_method_updates_correctly(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'webauthn_enabled' => false,
            'two_factor_confirmed_at' => null,
        ]);

        // Initially no MFA
        $this->assertEquals('none', $user->preferred_mfa_method);

        // Enable 2FA
        $user->update(['two_factor_confirmed_at' => now()]);
        $this->assertEquals('totp', $user->fresh()->preferred_mfa_method);

        // Enable WebAuthn with credentials
        $user->update(['webauthn_enabled' => true]);
        WebAuthnCredential::factory()->create([
            'authenticatable_id' => $user->id,
            'authenticatable_type' => User::class,
        ]);

        $this->assertEquals('webauthn', $user->fresh()->preferred_mfa_method);
    }

    public function test_webauthn_settings_page_shows_correct_user_data(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'webauthn_enabled' => true,
            'preferred_mfa_method' => 'webauthn',
        ]);

        $credential = WebAuthnCredential::factory()->create([
            'authenticatable_id' => $user->id,
            'authenticatable_type' => User::class,
            'alias' => 'iPhone 15',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/settings/webauthn');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('settings/web-authn')
                ->where('user.webauthn_enabled', true)
                ->where('user.preferred_mfa_method', 'webauthn')
                ->has('credentials', 1)
                ->where('credentials.0.name', 'iPhone 15')
        );
    }

    public function test_guest_cannot_access_webauthn_settings(): void
    {
        $response = $this->get('/settings/webauthn');

        $response->assertRedirect('/login');
    }

    public function test_guest_cannot_enable_webauthn(): void
    {
        $response = $this->post('/user/webauthn/enable');

        $response->assertRedirect('/login');
    }

    public function test_guest_cannot_disable_webauthn(): void
    {
        $response = $this->delete('/user/webauthn/disable');

        $response->assertRedirect('/login');
    }

    public function test_guest_cannot_fetch_credentials(): void
    {
        $response = $this->get('/user/webauthn/credentials');

        $response->assertRedirect('/login');
    }

    public function test_disabling_webauthn_without_2fa_sets_preferred_method_to_none(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'webauthn_enabled' => true,
            'two_factor_confirmed_at' => null, // No 2FA enabled
        ]);

        // Simulate password confirmation
        session(['auth.password_confirmed_at' => time()]);

        $response = $this
            ->actingAs($user)
            ->delete('/user/webauthn/disable');

        $response->assertOk();

        $user->refresh();
        $this->assertFalse($user->webauthn_enabled);
        $this->assertEquals('none', $user->preferred_mfa_method);
    }
}
