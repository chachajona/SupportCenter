<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\EmergencyAccessAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;

class EmergencyAccessController extends Controller
{
    /**
     * Show emergency access form.
     */
    public function show()
    {
        return Inertia::render('Auth/EmergencyAccess');
    }

    /**
     * Initiate emergency access procedure.
     */
    public function initiate(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'password' => 'required',
            'reason' => 'required|string|max:500',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }
        $plainToken = Str::random(32);
        $tokenHash = hash('sha256', $plainToken);
        $cacheKey = "emergency_access:{$tokenHash}";

        cache()->put($cacheKey, [
            'user_id' => $user->id,
            'reason' => $request->reason,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'expires_at' => now()->addHours(24),
        ], now()->addHours(24));

        // Send notification to user
        $user->notify(new EmergencyAccessAlert($plainToken, $request->reason));

        return back()->with('status', 'Emergency access request submitted. Check your email for further instructions.');
    }

    /**
     * Process emergency access token.
     */
    public function process(Request $request, string $token)
    {
        // Basic token validation to prevent obvious attacks and cache abuse
        if (empty($token) || strlen($token) > 64 || !preg_match('/^[a-zA-Z0-9\-_]+$/', $token)) {
            // Log suspicious token format attempt
            logger()->warning('Emergency access attempted with invalid token format', [
                'token_format' => 'invalid',
                'token_length' => strlen($token),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            abort(404, 'Emergency access token not found or expired.');
        }

        $emergencyData = cache()->get("emergency_access:{$token}");

        if (!$emergencyData) {
            // Log failed token attempt for security monitoring
            logger()->warning('Emergency access attempted with invalid/expired token', [
                'token_prefix' => substr($token, 0, 8) . '...',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            abort(404, 'Emergency access token not found or expired.');
        }

        $user = User::find($emergencyData['user_id']);

        if (!$user) {
            // Log security incident - token exists but user doesn't
            logger()->error('Emergency access token found but user missing', [
                'token_prefix' => substr($token, 0, 8) . '...',
                'user_id' => $emergencyData['user_id'],
                'ip_address' => $request->ip(),
            ]);

            abort(404, 'User not found.');
        }

        // Log successful emergency access
        logger()->info('Emergency access granted', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'reason' => $emergencyData['reason'],
            'original_ip' => $emergencyData['ip_address'],
            'access_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Log emergency access
        Auth::login($user);
        $request->session()->regenerate();

        // Set last activity time to prevent immediate session timeout
        $request->session()->put('last_activity_time', time());

        // SecurityLog::emergencyAccess($user->id, $request->ip(), $request->userAgent());

        // Clear the emergency token
        cache()->forget("emergency_access:{$token}");

        return redirect('/dashboard')->with('warning', 'Emergency access granted. Please secure your account by updating your authentication methods.');
    }
}
