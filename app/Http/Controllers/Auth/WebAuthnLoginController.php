<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
            session()->regenerate();

            return response()->json([
                'success' => true,
                'redirect' => config('fortify.home', '/dashboard'),
                'message' => "Welcome back, {$user->name}!"
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'WebAuthn authentication failed.'
        ], 422);
    }
}
