<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicProvider implements AIProviderInterface
{
    public function __construct(
        protected ?string $apiKey,
        protected string $baseUrl
    ) {
    }

    public function isConfigured(): bool
    {
        return (bool) $this->apiKey;
    }

    public function categorize(string $systemPrompt, string $userPrompt): ?array
    {
        $response = $this->sendMessage($systemPrompt, $userPrompt, [
            'max_tokens' => (int) config('services.anthropic.max_tokens', 800),
            'temperature' => (float) config('services.anthropic.temperature', 0.3),
        ]);

        return $this->extractJson($response);
    }

    public function suggestResponses(string $systemPrompt, string $userPrompt): ?string
    {
        $response = $this->sendMessage($systemPrompt, $userPrompt, [
            'max_tokens' => 800,
            'temperature' => (float) config('services.anthropic.temperature', 0.4),
        ]);

        if ($response && isset($response['content'][0]['text'])) {
            return $response['content'][0]['text'];
        }

        return null;
    }

    public function predictEscalation(string $systemPrompt, string $userPrompt): ?array
    {
        $response = $this->sendMessage($systemPrompt, $userPrompt, [
            'max_tokens' => 200,
            'temperature' => (float) config('services.anthropic.temperature', 0.2),
        ]);

        return $this->extractJson($response);
    }

    public function generateEmbeddings(string $text): array
    {
        // Anthropic currently does not provide embedding endpoint; return empty array.
        return [];
    }

    protected function sendMessage(string $systemPrompt, string $userPrompt, array $options): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $payload = array_merge([
                'model' => config('services.anthropic.default_model'),
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ], $options);

            $httpResponse = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ])->timeout(config('services.anthropic.timeout', 45))
                ->post($this->baseUrl . '/messages', $payload);

            if ($httpResponse->successful()) {
                return $httpResponse->json();
            }
        } catch (\Exception $e) {
            Log::error('Anthropic message failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    protected function extractJson(?array $response): ?array
    {
        if (!$response) {
            return null;
        }

        $content = $response['content'][0]['text'] ?? '';
        $result = json_decode($content, true);

        return json_last_error() === JSON_ERROR_NONE ? $result : null;
    }
}
