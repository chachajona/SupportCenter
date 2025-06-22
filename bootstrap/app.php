<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IdleSessionTimeout;
use App\Http\Middleware\SuspiciousActivityDetection;
use App\Http\Middleware\IpAllowlistMiddleware;
use App\Http\Middleware\RequirePasswordConfirmation;
use App\Http\Middleware\WebAuthnSecurityMiddleware;
use App\Http\Middleware\TwoFactorChallengeMiddleware;
use App\Http\Middleware\RolePermissionMiddleware;
use App\Http\Middleware\SetupMiddleware;
use App\Http\Middleware\PreventSetupAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            IdleSessionTimeout::class,
            SuspiciousActivityDetection::class,
            \App\Http\Middleware\GeoRestrictionMiddleware::class,
            \App\Http\Middleware\DeviceRegistrationMiddleware::class,
            \App\Http\Middleware\AdvancedRateLimiter::class,
        ]);

        $middleware->alias([
            'password.confirm' => RequirePasswordConfirmation::class,
            'ip.allowlist' => IpAllowlistMiddleware::class,
            'webauthn.security' => WebAuthnSecurityMiddleware::class,
            'two-factor.challenge' => TwoFactorChallengeMiddleware::class,
            'permission' => RolePermissionMiddleware::class,
            'geo.restrict' => \App\Http\Middleware\GeoRestrictionMiddleware::class,
            'device.register' => \App\Http\Middleware\DeviceRegistrationMiddleware::class,
            'rate.limit.operations' => \App\Http\Middleware\AdvancedRateLimiter::class,
            'setup.completed' => SetupMiddleware::class,
            'prevent.setup.access' => PreventSetupAccess::class,
        ]);

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
