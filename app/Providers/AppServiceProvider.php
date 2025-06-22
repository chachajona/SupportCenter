<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register a single global rate limiter for any authenticated/guest operation requests.
        // Keeping registration here avoids re-declaring the limiter on every request (middleware).
        RateLimiter::for('operation', function (Request $request) {
            // 50 requests per minute per user (or IP if guest)
            return Limit::perMinute(50)->by($request->user()?->id ?? $request->ip());
        });
    }
}
