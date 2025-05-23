<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FALaravel\Facade as Google2FAFacade;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;
use Illuminate\Support\Str;

class CustomTwoFactorQrCodeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function enableTwoFactorForUser(User $user, bool $andConfirm = false): void
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        // Generate 8 random recovery codes, each 10 characters long
        $recoveryCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $recoveryCodes[] = Str::random(10);
        }

        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($recoveryCodes)),
            'two_factor_confirmed_at' => $andConfirm ? now() : null,
        ])->save();
    }

    public function test_authenticated_user_can_get_qr_code_when_2fa_pending_confirmation(): void
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->enableTwoFactorForUser($user, andConfirm: false);

        $response = $this->actingAs($user)->get(route('custom-two-factor.qr-code'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $response->content());
    }

    public function test_returns_error_if_2fa_is_not_enabled_for_user(): void
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create(); // 2FA not enabled by default

        $response = $this->actingAs($user)->getJson(route('custom-two-factor.qr-code'));

        $response->assertStatus(400);
        $response->assertJson(['message' => 'Two-factor authentication is not pending confirmation.']);
    }

    public function test_returns_error_if_2fa_is_already_confirmed(): void
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->enableTwoFactorForUser($user, andConfirm: true);

        $response = $this->actingAs($user)->getJson(route('custom-two-factor.qr-code'));

        $response->assertStatus(400);
        $response->assertJson(['message' => 'Two-factor authentication is not pending confirmation.']);
    }

    public function test_unauthenticated_user_cannot_access_qr_code(): void
    {
        $response = $this->getJson(route('custom-two-factor.qr-code'));

        $response->assertStatus(401); // Expecting unauthenticated
    }

    public function test_qr_code_contains_correct_otpauth_url_details(): void
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create([
            'email' => 'testuser@example.com',
        ]);

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey(16); // Plain secret

        // Assign plain secret, the User model's mutator (from TwoFactorAuthenticatable trait)
        // will handle encryption.
        $user->forceFill([
            'two_factor_secret' => $secret,
        ])->save();

        $user->refresh(); // Ensure the user instance is fresh for the controller

        Google2FAFacade::shouldReceive('getQRCodeUrl')
            ->once()
            ->with(config('app.name'), $user->email, $secret) // Expect plain secret
            ->andReturn(sprintf(
                'otpauth://totp/%s:%s?secret=%s&issuer=%s',
                rawurlencode(config('app.name')),
                rawurlencode($user->email),
                $secret,
                rawurlencode(config('app.name'))
            ));

        $response = $this->actingAs($user)->get(route('custom-two-factor.qr-code'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/svg+xml');
    }
}
