<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;

class WebAuthnRegisterController extends Controller
{
    /**
     * Show the WebAuthn registration page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/WebAuthnRegister', [
            'user' => $request->user()->only(['id', 'name', 'email']),
            'credentials' => $request->user()->webAuthnCredentials()
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
     * Generate WebAuthn attestation options for registration.
     */
    public function options(AttestationRequest $request): JsonResponse
    {
        return response()->json(
            $request->toCreate()
        );
    }

    /**
     * Store a new WebAuthn credential.
     */
    public function store(AttestedRequest $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $credential = $request->save([
            'alias' => $request->input('name')
        ]);

        return response()->json([
            'success' => true,
            'credential' => [
                'id' => $credential->id,
                'name' => $credential->alias,
                'type' => 'security-key',
                'created_at' => $credential->created_at,
            ],
            'message' => 'Passkey registered successfully!'
        ]);
    }
}
