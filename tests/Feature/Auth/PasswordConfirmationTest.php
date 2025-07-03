<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_screen_can_be_rendered(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/confirm-password');

        $response->assertStatus(200);
    }

    public function test_password_can_be_confirmed(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/confirm-password', [
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    public function test_password_is_not_confirmed_with_invalid_password(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/confirm-password', [
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors();
    }

    public function test_password_confirmation_shows_intended_parameter(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/confirm-password?intended='.urlencode('/settings/webauthn?resume_webauthn=setup'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page->component('auth/confirm-password')
                ->has('intended')
                ->where('intended', '/settings/webauthn?resume_webauthn=setup')
        );
    }

    public function test_password_confirmation_redirects_to_intended_url(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $intendedUrl = '/settings/webauthn?resume_webauthn=setup';

        // First, set the intended URL by visiting the confirmation page
        $this->actingAs($user)->get('/confirm-password?intended='.urlencode($intendedUrl));

        // Then submit the password confirmation
        $response = $this->actingAs($user)->post('/confirm-password', [
            'password' => 'password',
        ]);

        $response->assertRedirect($intendedUrl);
    }

    public function test_password_confirmation_redirects_to_dashboard_when_no_intended_url(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/confirm-password', [
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
    }

    public function test_password_confirmation_fails_with_wrong_password(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/confirm-password', [
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('password');
    }
}
