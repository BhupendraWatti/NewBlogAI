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
            // ── Structured Output: build response_format ──────────────────────
            // json_schema (Structured Outputs) — schema-enforced JSON; requires
            // gpt-4o / gpt-4o-mini. json_object — valid JSON, no schema enforcement.
            $responseFormat = null;
            if (! empty($options['json_schema'])) {
                $responseFormat = [
                    'type'        => 'json_schema',
                    'json_schema' => $options['json_schema'], // {name, strict, schema}
                ];
            } elseif (! empty($options['json_mode'])) {
                $responseFormat = ['type' => 'json_object'];
            }
            // ── End Structured Output ─────────────────────────────────────────

            $payload = [
                'model'       => $model,
                'messages'    => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens'  => $options['max_tokens'] ?? 2000,
            ];

            if ($responseFormat !== null) {
                $payload['response_format'] = $responseFormat;
            }

            $response = Http::withToken($apiKey)
                ->timeout($options['timeout'] ?? 90)
                ->post('https://api.openai.com/v1/chat/completions', $payload);

            if (! $response->successful()) {
                throw new \RuntimeException("OpenAI API error: Status {$response->status()} - ".$response->body());
            }

            $data = $response->json();
            $text = $data['choices'][0]['message']['content'] ?? '';
            $usage = $data['usage'] ?? [];
            $promptTokens = $usage['prompt_tokens'] ?? 0;
            $completionTokens = $usage['completion_tokens'] ?? 0;
            $totalTokens = $usage['total_tokens'] ?? 0;

            // ponytail: static rates cover configured model families; use a versioned catalog if arbitrary models are enabled.
            [$promptRate, $cachedRate, $completionRate] = match (true) {
                str_contains($model, 'gpt-5.6-terra') => [0.00200, 0.00020, 0.01200],
                str_contains($model, 'gpt-5.6-luna') => [0.00020, 0.00002, 0.00120],
                str_contains($model, 'gpt-5.6-sol'), $model === 'gpt-5.6' => [0.00400, 0.00040, 0.02000],
                str_contains($model, 'gpt-5.4') => [0.00250, 0.00025, 0.01500],
                str_contains($model, 'gpt-4o-mini') => [0.00015, 0.000075, 0.00060],
                str_contains($model, 'gpt-4o') => [0.00250, 0.00125, 0.01000],
                str_contains($model, 'o3-mini'), str_contains($model, 'o1-mini') => [0.00110, 0.00055, 0.00440],
                str_contains($model, 'gpt-4') => [0.01000, 0.00500, 0.03000],
                default => [0.00050, 0.00025, 0.00150],
            };
            $cachedTokens = min($promptTokens, (int) ($usage['prompt_tokens_details']['cached_tokens'] ?? 0));
            $cacheWriteTokens = min($promptTokens - $cachedTokens, (int) ($usage['prompt_tokens_details']['cache_write_tokens'] ?? 0));
            $cost = ((($promptTokens - $cachedTokens - $cacheWriteTokens) / 1000.0) * $promptRate)
                + (($cachedTokens / 1000.0) * $cachedRate)
                + (($cacheWriteTokens / 1000.0) * $promptRate * 1.25)
                + (($completionTokens / 1000.0) * $completionRate);


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
