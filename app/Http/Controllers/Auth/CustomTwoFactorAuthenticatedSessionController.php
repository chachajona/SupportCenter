<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Contracts\TwoFactorChallengeViewResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;
use Symfony\Component\HttpFoundation\Response;

final class CustomTwoFactorAuthenticatedSessionController extends TwoFactorAuthenticatedSessionController
{
    /**
     * Show the two factor authentication challenge view.
     *
     * This method delegates to Fortify's parent implementation to maintain
     * complete compatibility with the authentication flow.
     */
    public function create(Request $request): TwoFactorChallengeViewResponse
    {
        // Ensure we have a valid TwoFactorLoginRequest, convert if needed
        if (! $request instanceof TwoFactorLoginRequest) {
            $twoFactorRequest = TwoFactorLoginRequest::createFrom($request);
        } else {
            $twoFactorRequest = $request;
        }

        // Delegate to parent to ensure proper view rendering and middleware handling
        return parent::create($twoFactorRequest);
    }

    /**
     * Attempt to authenticate using a two factor authentication code.
     *
     * This method extends Fortify's base implementation to add custom session
     * management while maintaining full compatibility with Fortify's response system.
     */
    public function store(Request $request): Response
    {
        // Ensure we have a valid TwoFactorLoginRequest, convert if needed
        if (! $request instanceof TwoFactorLoginRequest) {
            $twoFactorRequest = TwoFactorLoginRequest::createFrom($request);
        } else {
            $twoFactorRequest = $request;
        }

        // Store the authentication state before attempting 2FA
        $wasAuthenticated = Auth::check();
        $userId = Auth::id();

        // Call parent store method to handle 2FA authentication
        $response = parent::store($twoFactorRequest);

        // Check if 2FA was successful by comparing authentication state
        // Only proceed if user was not fully authenticated before and is now
        $isNowAuthenticated = Auth::check();
        $currentUserId = Auth::id();

        // Handle successful 2FA authentication
        // We check if authentication state changed or if we have a proper TwoFactorLoginResponse
        if (
            $response instanceof TwoFactorLoginResponse ||
            (! $wasAuthenticated && $isNowAuthenticated) ||
            ($userId !== $currentUserId && $isNowAuthenticated)
        ) {

            $this->handleSuccessfulAuthentication($twoFactorRequest);

            Log::info('Two-factor authentication successful', [
                'user_id' => $currentUserId,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        // Return the original Fortify response to maintain full compatibility
        // Convert to proper Response if needed
        if ($response instanceof TwoFactorLoginResponse) {
            return $response->toResponse($request);
        }

        return $response;
    }

    /**
     * Handle post-authentication tasks for successful 2FA login.
     *
     * This method encapsulates the business logic for successful authentication,
     * following the Single Responsibility Principle and making the code more testable.
     */
    private function handleSuccessfulAuthentication(TwoFactorLoginRequest $request): void
    {
        // Set last activity time to prevent immediate session timeout
        // This is crucial for maintaining proper session state after 2FA
        $request->session()->put('last_activity_time', time());

        // Regenerate session ID for security (prevent session fixation)
        $request->session()->regenerate();

        // Clear any temporary 2FA-related session data for security
        $request->session()->forget(['login.id', 'login.remember']);
    }
}
