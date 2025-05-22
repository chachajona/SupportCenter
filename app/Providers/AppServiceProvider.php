<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Log;
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
        if ($this->app->runningUnitTests()) {
            Event::listen(RouteMatched::class, function (RouteMatched $event) {
                if ($event->request->method() === 'POST' && $event->request->is('two-factor-challenge')) {
                    $actionName = $event->route->getActionName();
                    Log::debug('[RouteMatched_EVENT] Route matched for POST /two-factor-challenge. Action: ' . $actionName);

                    // Log all middleware names for this specific route
                    $middleware = $event->route->gatherMiddleware(); // Gets all middleware for the route
                    Log::debug('[RouteMatched_EVENT_MIDDLEWARE] Middleware for /two-factor-challenge: ' . implode(', ', $middleware));
                }
            });

            // Log all events during tests
            Event::listen('*_*', function ($eventName, $data) {
                if (is_object($data) && method_exists($data, '__toString')) {
                    // Log::debug("[WILDCARD_EVENT] Event: {$eventName}, Data: " . $data->__toString());
                } elseif (is_array($data) && isset($data[0]) && is_object($data[0]) && method_exists($data[0], '__toString')) {
                    // Log::debug("[WILDCARD_EVENT] Event: {$eventName}, Data (first item): " . $data[0]->__toString());
                } else {
                    // Log::debug("[WILDCARD_EVENT] Event: {$eventName}");
                }
            });
        }
    }
}
