<?php

namespace App\Providers;

use App\Models\Ticket;
use App\Observers\TicketObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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

        // Register model observers
        Ticket::observe(TicketObserver::class);
    }
}
