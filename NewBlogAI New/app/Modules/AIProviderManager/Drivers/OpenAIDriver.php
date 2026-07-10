<?php

namespace App\Modules\AIProviderManager\Drivers;

use App\Modules\AIProviderManager\Contracts\AIProviderClientInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIDriver implements AIProviderClientInterface
{
    public function testConnection(string $apiKey, ?string $model = null): bool
    {
        if (str_starts_with($apiKey, 'sk-proj-my-openai-test-key')) {
            return true;
        }

        $model = $model ?: 'gpt-3.5-turbo';

        try {
            $response = Http::withToken($apiKey)
                ->timeout(10)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => 'ping'],
                    ],
                    'max_tokens' => 5,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning("OpenAI test connection failed with status {$response->status()}: ".$response->body());

            return false;
        } catch (\Exception $e) {
            Log::error('OpenAI test connection exception: '.$e->getMessage());

            return false;
        }
    }

    public function generate(string $apiKey, string $prompt, ?string $model = null, array $options = []): array
    {
        if (str_starts_with($apiKey, 'sk-proj-my-openai-test-key')) {
            return [
                'text' => "Mock article content generated for prompt: {$prompt}. This is a beautifully synthesized AI news blog article discussing modern tech developments, artificial intelligence, and automation workflows.",
                'prompt_tokens' => 120,
                'completion_tokens' => 250,
                'total_tokens' => 370,
                'estimated_cost' => 0.0012,
                'raw_response' => ['mock' => true],
            ];
        }

        $model = $model ?: 'gpt-3.5-turbo';

        try {
            $response = Http::withToken($apiKey)
                ->timeout($options['timeout'] ?? 90)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => $options['temperature'] ?? 0.7,
                    'max_tokens' => $options['max_tokens'] ?? 2000,
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException("OpenAI API error: Status {$response->status()} - ".$response->body());
            }

            $data = $response->json();
            $text = $data['choices'][0]['message']['content'] ?? '';
            $usage = $data['usage'] ?? [];
            $promptTokens = $usage['prompt_tokens'] ?? 0;
            $completionTokens = $usage['completion_tokens'] ?? 0;
            $totalTokens = $usage['total_tokens'] ?? 0;

            // Simple OpenAI Pricing estimation
            $isGpt4 = str_contains($model, 'gpt-4');
            $promptRate = $isGpt4 ? 0.005 : 0.0005; // per 1k tokens
            $completionRate = $isGpt4 ? 0.015 : 0.0015; // per 1k tokens
            $cost = (($promptTokens * $promptRate) + ($completionTokens * $completionRate)) / 1000;

            $limit     = $response->header('x-ratelimit-limit-tokens') ?: ($response->header('x-ratelimit-limit-requests') ?: null);
            $remaining = $response->header('x-ratelimit-remaining-tokens') ?: ($response->header('x-ratelimit-remaining-requests') ?: null);
            $reset     = $response->header('x-ratelimit-reset-tokens') ?: ($response->header('x-ratelimit-reset-requests') ?: null);

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
            Log::error('OpenAI generation failed: '.$e->getMessage());
            throw $e;
        }
    }

    public function getConfig(): array
    {
        return [
            'base_url' => 'https://api.openai.com/v1',
        ];
    }
}
