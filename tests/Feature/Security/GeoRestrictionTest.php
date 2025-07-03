<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class GeoRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Define a temporary route that applies the GeoRestriction middleware for testing
        Route::middleware([\App\Http\Middleware\GeoRestrictionMiddleware::class])
            ->get('/geo-check', fn () => response()->json(['message' => 'OK']))
            ->name('geo.check');
    }

    public function test_allows_request_from_allowed_country(): void
    {
        $this->app['env'] = 'production';

        // Fake GeoIP response as an allowed country (US)
        Http::fake([
            'ipapi.co/*' => Http::response('US', 200),
        ]);

        $this->get('/geo-check', [
            'REMOTE_ADDR' => '203.0.113.10', // Public IP
        ])->assertOk()->assertJson(['message' => 'OK']);
    }

    public function test_blocks_request_from_disallowed_country(): void
    {
        $this->app['env'] = 'production';

        // Fake GeoIP response as a disallowed country (CN)
        Http::fake([
            'ipapi.co/*' => Http::response('CN', 200),
        ]);

        $this->get('/geo-check', [
            'REMOTE_ADDR' => '198.51.100.10', // Public IP
        ])->assertForbidden()->assertJson(['message' => 'Access from your region is not allowed.']);
    }

    public function test_skips_private_ip_addresses(): void
    {
        // No GeoIP call should be made, but set a default fake to catch any unexpected request.
        Http::fake([
            '*' => Http::response('', 500),
        ]);

        $this->get('/geo-check', [
            'REMOTE_ADDR' => '127.0.0.1',
        ])->assertOk();
    }
}
