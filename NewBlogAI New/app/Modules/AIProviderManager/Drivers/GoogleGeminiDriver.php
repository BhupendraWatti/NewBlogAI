<?php

namespace App\Modules\AIProviderManager\Drivers;

use App\Modules\AIProviderManager\Contracts\AIProviderClientInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleGeminiDriver implements AIProviderClientInterface
{
    public function testConnection(string $apiKey, ?string $model = null): bool
    {
        $model = $model ?: 'gemini-2.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        try {
            $response = Http::timeout(10)->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => 'ping'],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'maxOutputTokens' => 5,
                ],
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning("Gemini test connection failed with status {$response->status()}: ".$response->body());

            return false;
        } catch (\Exception $e) {
            Log::error('Gemini test connection exception: '.$e->getMessage());

            return false;
        }
    }

    public function generate(string $apiKey, string $prompt, ?string $model = null, array $options = []): array
    {
        $model = $model ?: 'gemini-2.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        try {
            $response = Http::timeout($options['timeout'] ?? 90)->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => $options['temperature'] ?? 0.7,
                    'maxOutputTokens' => $options['max_tokens'] ?? 2048,
                ],
            ]);

            if (! $response->successful()) {
                throw new \RuntimeException("Gemini API error: Status {$response->status()} - ".$response->body());
            }

            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Gemini beta has usageMetadata
            $usage = $data['usageMetadata'] ?? [];
            $promptTokens = $usage['promptTokenCount'] ?? 0;
            $completionTokens = $usage['candidatesTokenCount'] ?? 0;
            $totalTokens = $usage['totalTokenCount'] ?? 0;

            // Gemini Pricing estimation
            $isPro = str_contains($model, 'pro');
            $promptRate = $isPro ? 0.00125 : 0.000125;
            $completionRate = $isPro ? 0.00375 : 0.000375;
            $cost = (($promptTokens * $promptRate) + ($completionTokens * $completionRate)) / 1000;

            return [
                'text' => $text,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $totalTokens,
                'estimated_cost' => $cost,
                'raw_response' => $data,
            ];

        } catch (\Exception $e) {
            Log::error('Gemini generation failed: '.$e->getMessage());
            throw $e;
        }
    }

    public function getConfig(): array
    {
        return [
            'base_url' => 'https://generativelanguage.googleapis.com',
        ];
    }
}
