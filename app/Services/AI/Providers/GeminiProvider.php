<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AIProviderInterface
{
    public function __construct(
        protected ?string $apiKey,
        protected string $baseUrl
    ) {}

    public function isConfigured(): bool
    {
        return (bool) $this->apiKey;
    }

    public function categorize(string $systemPrompt, string $userPrompt): ?array
    {
        $response = $this->sendGenerateContentRequest($systemPrompt, $userPrompt, [
            'temperature' => (float) config('services.gemini.temperature'),
            'maxOutputTokens' => (int) config('services.gemini.max_tokens'),
            'responseMimeType' => 'application/json',
        ]);

        return $this->extractJson($response);
    }

    public function suggestResponses(string $systemPrompt, string $userPrompt): ?string
    {
        $response = $this->sendGenerateContentRequest($systemPrompt, $userPrompt, [
            'temperature' => 0.4,
            'maxOutputTokens' => 800,
        ]);

        if ($response && isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            return $response['candidates'][0]['content']['parts'][0]['text'];
        }

        return null;
    }

    public function predictEscalation(string $systemPrompt, string $userPrompt): ?array
    {
        $response = $this->sendGenerateContentRequest($systemPrompt, $userPrompt, [
            'temperature' => 0.2,
            'maxOutputTokens' => 200,
            'responseMimeType' => 'application/json',
        ]);

        return $this->extractJson($response);
    }

    public function generateEmbeddings(string $text): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $httpResponse = Http::withHeaders($this->buildAuthHeaders())
                ->timeout(30)
                ->post($this->baseUrl.'/models/'.config('services.gemini.embedding_model').':embedContent', [
                    'content' => [
                        'parts' => [
                            ['text' => $text],
                        ],
                    ],
                    'taskType' => 'SEMANTIC_SIMILARITY',
                ]);

            if ($httpResponse->successful()) {
                $responseData = $httpResponse->json();

                return $responseData['embedding']['values'] ?? [];
            }
        } catch (\Exception $e) {
            Log::error('Gemini embedding generation failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    /**
     * Send a generateContent request to Gemini and return decoded JSON.
     */
    protected function sendGenerateContentRequest(string $systemPrompt, string $userPrompt, array $generationConfig): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $httpResponse = Http::withHeaders($this->buildAuthHeaders())
                ->timeout($generationConfig['timeout'] ?? 45)
                ->post($this->baseUrl.'/models/'.config('services.gemini.default_model').':generateContent', [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $systemPrompt."\n\n".$userPrompt],
                            ],
                        ],
                    ],
                    'generationConfig' => $generationConfig,
                    'safetySettings' => $this->getSafetySettings(),
                ]);

            if ($httpResponse->successful()) {
                return $httpResponse->json();
            }
        } catch (\Exception $e) {
            Log::error('Gemini generateContent failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    protected function extractJson(?array $response): ?array
    {
        if (! $response) {
            return null;
        }

        $content = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $result = json_decode($content, true);

        return json_last_error() === JSON_ERROR_NONE ? $result : null;
    }

    protected function buildAuthHeaders(): array
    {
        return [
            'x-goog-api-key' => $this->apiKey,
        ];
    }

    protected function getSafetySettings(): array
    {
        $settings = config('services.gemini.safety_settings', []);
        $formatted = [];

        foreach ($settings as $category => $threshold) {
            $formatted[] = [
                'category' => $category,
                'threshold' => $threshold,
            ];
        }

        return $formatted;
    }
}
