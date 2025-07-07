<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AI\MachineLearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class MachineLearningServiceTest extends TestCase
{
    use RefreshDatabase;

    private MachineLearningService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_categorize_ticket_with_gemini_provider(): void
    {
        // Arrange: configure provider & fake HTTP
        Config::set('services.ai_provider', 'gemini');
        Config::set('services.gemini.api_key', 'dummy-key');

        $fakePayload = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'department' => 'billing',
                                    'priority' => 'normal',
                                    'category' => 'question',
                                    'estimated_resolution_time' => 60,
                                    'sentiment' => 'neutral',
                                    'confidence' => 0.9,
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        Http::fake([
            '*' => Http::response($fakePayload, 200),
        ]);

        $this->service = resolve(MachineLearningService::class);

        // Act
        $result = $this->service->categorizeTicket('Billing question', 'I have an issue with my invoice');

        // Assert
        $this->assertSame('billing', $result['department']);
        $this->assertSame('question', $result['category']);
        $this->assertSame(0.9, $result['confidence']);
    }

    public function test_categorize_ticket_with_anthropic_provider(): void
    {
        // Arrange
        Config::set('services.ai_provider', 'anthropic');
        Config::set('services.anthropic.api_key', 'dummy-key');

        $fakePayload = [
            'content' => [
                [
                    'text' => json_encode([
                        'department' => 'technical',
                        'priority' => 'high',
                        'category' => 'bug',
                        'estimated_resolution_time' => 120,
                        'sentiment' => 'negative',
                        'confidence' => 0.95,
                    ]),
                ],
            ],
        ];

        Http::fake([
            '*' => Http::response($fakePayload, 200),
        ]);

        $this->service = resolve(MachineLearningService::class);

        // Act
        $result = $this->service->categorizeTicket('Bug report', 'The system crashes occasionally');

        // Assert
        $this->assertSame('technical', $result['department']);
        $this->assertSame('bug', $result['category']);
        $this->assertSame(0.95, $result['confidence']);
    }

    public function test_fallback_categorization_when_no_api_key(): void
    {
        // Arrange: Ensure no API keys available
        Config::set('services.ai_provider', 'gemini');
        Config::set('services.gemini.api_key', null);
        Config::set('services.anthropic.api_key', null);

        Http::preventStrayRequests();

        $this->service = resolve(MachineLearningService::class);

        // Act
        $result = $this->service->categorizeTicket('Payment error', 'There is an error on my bill');

        // Assert (fallback picks up "error" keyword as technical)
        $this->assertSame('technical', $result['department']);
        $this->assertSame('question', $result['category']);
        $this->assertSame(0.3, $result['confidence']);
    }
}
