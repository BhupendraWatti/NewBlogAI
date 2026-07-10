<?php

namespace App\Modules\AIProviderManager\Drivers;

use App\Modules\AIProviderManager\Contracts\AIProviderClientInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeDriver implements AIProviderClientInterface
{
    public function testConnection(string $apiKey, ?string $model = null): bool
    {
        $model = $model ?: 'claude-3-5-sonnet-20241022';

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
                ->timeout(10)
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => 'ping'],
                    ],
                    'max_tokens' => 5,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning("Claude test connection failed with status {$response->status()}: ".$response->body());

            return false;
        } catch (\Exception $e) {
            Log::error('Claude test connection exception: '.$e->getMessage());

            return false;
        }
    }

    public function generate(string $apiKey, string $prompt, ?string $model = null, array $options = []): array
    {
        $model = $model ?: 'claude-3-5-sonnet-20241022';

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
                ->timeout($options['timeout'] ?? 90)
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => $options['temperature'] ?? 0.7,
                    'max_tokens' => $options['max_tokens'] ?? 2000,
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException("Claude API error: Status {$response->status()} - ".$response->body());
            }

            $data = $response->json();
            $text = $data['content'][0]['text'] ?? '';
            $usage = $data['usage'] ?? [];
            $promptTokens = $usage['input_tokens'] ?? 0;
            $completionTokens = $usage['output_tokens'] ?? 0;
            $totalTokens = $promptTokens + $completionTokens;

            // Claude Pricing estimation (Sonnet 3.5 defaults)
            $cost = (($promptTokens * 0.003) + ($completionTokens * 0.015)) / 1000;

            $limit = $response->header('anthropic-ratelimit-tokens-limit') ?: $response->header('anthropic-ratelimit-requests-limit');
            $remaining = $response->header('anthropic-ratelimit-tokens-remaining') ?: $response->header('anthropic-ratelimit-requests-remaining');
            $reset = $response->header('anthropic-ratelimit-tokens-reset') ?: $response->header('anthropic-ratelimit-requests-reset');

            return [
                'text' => $text,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $totalTokens,
                'estimated_cost' => $cost,
                'raw_response' => $data,
                'rate_limits' => [
                    'limit' => $limit,
                    'remaining' => $remaining,
                    'reset' => $reset,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Claude generation failed: '.$e->getMessage());
            throw $e;
        }
    }

    public function getConfig(): array
    {
        return [
            'base_url' => 'https://api.anthropic.com/v1',
        ];
    }
}
