<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SecurityEventType;
use App\Http\Controllers\Controller;
use App\Models\SecurityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Laragear\WebAuthn\Http\Requests\AssertionRequest;
use Laragear\WebAuthn\Http\Requests\AssertedRequest;

class WebAuthnLoginController extends Controller
{
    /**
     * Generate WebAuthn assertion options for login.
     */
    public function options(AssertionRequest $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        return response()->json(
            $request->toVerify($request->only('email'))
        );
    }

    /**
     * Authenticate user with WebAuthn assertion.
     */
    public function authenticate(AssertedRequest $request): JsonResponse
    {
        $user = $request->login();

        if ($user) {
            // Log successful WebAuthn login
            SecurityLog::logWebAuthnEvent(
                SecurityEventType::WEBAUTHN_LOGIN,
                $user,
                $request,
                ['success' => true, 'method' => 'webauthn']
            );

            session()->regenerate();

            // Set last activity time to prevent immediate session timeout
            session()->put('last_activity_time', time());

            return response()->json([
                'success' => true,
                'redirect' => config('fortify.home', '/dashboard'),
                'message' => "Welcome back, {$user->name}!"
            ]);
        }

        // Log failed WebAuthn attempt
        SecurityLog::logWebAuthnEvent(
            SecurityEventType::WEBAUTHN_FAILED,
            null,
            $request,
            ['success' => false, 'reason' => 'authentication_failed']
        );

        return response()->json([
            'success' => false,
            'message' => 'WebAuthn authentication failed.'
        ], 422);
    }
}
