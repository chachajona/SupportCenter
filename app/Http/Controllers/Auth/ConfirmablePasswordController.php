<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ConfirmablePasswordController extends Controller
{
    /**
     * Show the confirm password page.
     */
    public function show(Request $request): Response
    {
        $intendedUrl = $request->get('intended');

        // Validate and sanitize the intended URL to prevent open redirect vulnerabilities
        if ($intendedUrl && $this->isValidIntendedUrl($intendedUrl)) {
            $request->session()->put('url.intended', $intendedUrl);
            $sanitizedIntended = $this->sanitizeUrl($intendedUrl);
        } else {
            $sanitizedIntended = null;
        }

        return Inertia::render('auth/confirm-password', [
            'intended' => $sanitizedIntended,
        ]);
    }

    /**
     * Confirm the user's password.
     */
    public function store(Request $request): RedirectResponse
    {
        if (
            !Auth::guard('web')->validate([
                'email' => $request->user()->email,
                'password' => $request->password,
            ])
        ) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Validate that the intended URL is safe to redirect to.
     * Prevents open redirect vulnerabilities by ensuring the URL is relative
     * or belongs to the application domain.
     */
    private function isValidIntendedUrl(string $url): bool
    {
        // Allow relative URLs that start with /
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }

        // Parse the URL to check if it's absolute
        $parsedUrl = parse_url($url);

        // If parsing fails, reject the URL
        if ($parsedUrl === false) {
            return false;
        }

        // If no host is present, it's likely a relative URL (but be cautious)
        if (!isset($parsedUrl['host'])) {
            // Additional check for protocol-relative URLs (//example.com)
            return !str_starts_with($url, '//');
        }

        // For absolute URLs, check if the host matches the application domain
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);

        return $parsedUrl['host'] === $appHost;
    }

    /**
     * Sanitize the URL to prevent XSS vulnerabilities.
     * Encodes potentially harmful characters while preserving URL functionality.
     */
    private function sanitizeUrl(string $url): string
    {
        // Remove any null bytes and control characters
        $url = preg_replace('/[\x00-\x1F\x7F]/', '', $url);

        // HTML encode to prevent XSS in frontend rendering
        return htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
