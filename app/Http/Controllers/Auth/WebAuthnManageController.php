<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SecurityEventType;
use App\Http\Controllers\Controller;
use App\Models\SecurityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Laragear\WebAuthn\Models\WebAuthnCredential;

class WebAuthnManageController extends Controller
{
    /**
     * Delete a WebAuthn credential.
     */
    public function destroy(Request $request, WebAuthnCredential $credential): JsonResponse
    {
        // Ensure the credential belongs to the authenticated user
        if ($credential->authenticatable_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        // Log credential removal before deletion
        SecurityLog::logWebAuthnEvent(
            SecurityEventType::WEBAUTHN_REMOVE,
            $request->user(),
            $request,
            [
                'success' => true,
                'credential_id' => $credential->id,
                'credential_name' => $credential->alias ?? 'Unnamed Device',
            ]
        );

        $credential->delete();

        return response()->json([
            'success' => true,
            'message' => 'Passkey removed successfully!'
        ]);
    }

    /**
     * Update a WebAuthn credential.
     */
    public function update(Request $request, WebAuthnCredential $credential): JsonResponse
    {
        // Ensure the credential belongs to the authenticated user
        if ($credential->authenticatable_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $credential->update([
            'alias' => $request->input('name')
        ]);

        return response()->json([
            'success' => true,
            'credential' => [
                'id' => $credential->id,
                'name' => $credential->alias,
                'type' => 'security-key',
                'created_at' => $credential->created_at,
                'last_used_at' => $credential->updated_at,
            ],
            'message' => 'Passkey updated successfully!'
        ]);
    }
}
