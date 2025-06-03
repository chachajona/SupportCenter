<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorChoiceController extends Controller
{
    /**
     * Show the two-factor authentication method choice page.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $user = session('login.id') ? User::find(session('login.id')) : null;

        if (!$user) {
            return redirect()->route('login');
        }

        $availableMethods = $this->getAvailableMethods($user);

        // If only one method available, redirect directly
        if (count($availableMethods) === 1) {
            return $this->redirectToMethod($availableMethods[0]);
        }

        // If no methods available, redirect to login with error
        if (empty($availableMethods)) {
            return redirect()->route('login')
                ->withErrors(['email' => 'No two-factor authentication methods available.']);
        }

        return Inertia::render('Auth/TwoFactorChoice', [
            'user' => $user->only(['name', 'email']),
            'availableMethods' => $availableMethods,
            'canUseRecoveryCode' => $user->two_factor_enabled,
        ]);
    }

    /**
     * Handle method selection.
     */
    public function select(Request $request)
    {
        $request->validate([
            'method' => 'required|in:totp,webauthn,recovery'
        ]);

        $method = $request->input('method');

        return $this->redirectToMethod($method);
    }

    private function getAvailableMethods(User $user): array
    {
        $methods = [];

        if ($user->two_factor_enabled) {
            $methods[] = 'totp';
        }

        if ($user->hasWebAuthnCredentials()) {
            $methods[] = 'webauthn';
        }

        return $methods;
    }

    private function redirectToMethod(string $method)
    {
        return match ($method) {
            'totp' => redirect()->route('two-factor.login'),
            'webauthn' => redirect()->route('webauthn.login'),
            'recovery' => redirect()->route('two-factor.login'),
            default => redirect()->route('login'),
        };
    }
}
