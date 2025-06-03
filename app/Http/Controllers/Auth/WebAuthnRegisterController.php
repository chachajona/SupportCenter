<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SecurityEventType;
use App\Http\Controllers\Controller;
use App\Models\SecurityLog;
use Illuminate\Http\JsonResponse;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;

class WebAuthnRegisterController extends Controller
{
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

        // Log successful WebAuthn credential registration
        SecurityLog::logWebAuthnEvent(
            SecurityEventType::WEBAUTHN_REGISTER,
            $request->user(),
            $request,
            [
                'success' => true,
                'credential_id' => (string) $credential->id,
                'credential_name' => (string) $credential->alias,
            ]
        );

        return response()->json([
            'success' => true,
            'credential' => [
                'id' => (string) $credential->id,
                'name' => (string) $credential->alias,
                'type' => 'security-key',
                'created_at' => $credential->created_at?->toISOString(),
            ],
            'message' => 'Passkey registered successfully!'
        ]);
    }
}
