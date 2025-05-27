<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class WebAuthnSettingsController extends Controller
{
    /**
     * Show the WebAuthn settings page.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/web-authn', [
            'user' => $user->only([
                'id',
                'name',
                'email',
                'webauthn_enabled',
                'preferred_mfa_method'
            ]),
            'credentials' => $user->webAuthnCredentials()
                ->whereEnabled()
                ->get()
                ->map(fn($credential) => [
                    'id' => $credential->id,
                    'name' => $credential->alias ?? 'Unnamed Device',
                    'type' => 'security-key', // You can enhance this based on actual device type
                    'created_at' => $credential->created_at,
                    'last_used_at' => $credential->updated_at,
                ])
        ]);
    }

    /**
     * Enable WebAuthn for the user.
     */
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        // Enable WebAuthn
        $user->update([
            'webauthn_enabled' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'WebAuthn enabled successfully.'
        ]);
    }

    /**
     * Disable WebAuthn for the user.
     */
    public function disable(Request $request): JsonResponse
    {
        $user = $request->user();

        // Disable all WebAuthn credentials
        $user->webAuthnCredentials()->update(['disabled_at' => now()]);

        // Update user preferences
        $user->update([
            'webauthn_enabled' => false,
            'preferred_mfa_method' => $user->two_factor_enabled ? 'totp' : 'none',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'WebAuthn disabled successfully.'
        ]);
    }

    /**
     * Get user's WebAuthn credentials.
     */
    public function credentials(Request $request): JsonResponse
    {
        $user = $request->user();

        $credentials = $user->webAuthnCredentials()
            ->whereEnabled()
            ->get()
            ->map(fn($credential) => [
                'id' => $credential->id,
                'name' => $credential->alias ?? 'Unnamed Device',
                'type' => 'security-key',
                'created_at' => $credential->created_at,
                'last_used_at' => $credential->updated_at,
            ]);

        return response()->json($credentials);
    }
}
