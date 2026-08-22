<?php

namespace App\Modules\AIProviderManager\Drivers;

use App\Modules\AIProviderManager\Contracts\AIProviderClientInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterDriver implements AIProviderClientInterface
{
    public function testConnection(string $apiKey, ?string $model = null): bool
    {
        $model = $model ?: 'google/gemini-pro';

        try {
            $response = Http::withToken($apiKey)
                ->withHeaders([
                    'HTTP-Referer' => config('app.url', 'http://localhost'),
                    'X-Title' => 'NewsBlogify AI OS',
                ])
                ->timeout(10)
                ->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => 'ping'],
                    ],
                    'max_tokens' => 5,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning("OpenRouter test connection failed with status {$response->status()}: ".$response->body());

            return false;
        } catch (\Exception $e) {
            Log::error('OpenRouter test connection exception: '.$e->getMessage());

            return false;
        }
    }

    public function generate(string $apiKey, string $prompt, ?string $model = null, array $options = []): array
    {
        $model = $model ?: 'google/gemini-pro';

        try {
            // -- Structured Output: response_format --------------------------------
            // OpenRouter forwards response_format to the underlying model.
            // json_schema mode requires a model that supports Structured Outputs.
            // json_object guarantees valid JSON with no schema enforcement.
            $responseFormat = null;
            if (! empty($options['json_schema'])) {
                $responseFormat = [
                    'type'        => 'json_schema',
                    'json_schema' => $options['json_schema'],
                ];
            } elseif (! empty($options['json_mode'])) {
                $responseFormat = ['type' => 'json_object'];
            }
            // -- End Structured Output -------------------------------------------

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
                ->withHeaders([
                    'HTTP-Referer' => config('app.url', 'http://localhost'),
                    'X-Title' => 'NewsBlogify AI OS',
                ])
                ->timeout($options['timeout'] ?? 90)
                ->post('https://openrouter.ai/api/v1/chat/completions', $payload);

            if (! $response->successful()) {
                throw new \RuntimeException("OpenRouter API error: Status {$response->status()} - ".$response->body());
            }

            $data = $response->json();
            $text = $data['choices'][0]['message']['content'] ?? '';
            $usage = $data['usage'] ?? [];
            $promptTokens = $usage['prompt_tokens'] ?? 0;
            $completionTokens = $usage['completion_tokens'] ?? 0;
            $totalTokens = $usage['total_tokens'] ?? 0;

            // OpenRouter natively returns usage.cost in USD when available
            if (isset($usage['cost']) && is_numeric($usage['cost'])) {
                $cost = (float) $usage['cost'];
            } else {
                $promptRate     = 0.00050; // $0.50 / 1M input default
                $completionRate = 0.00150; // $1.50 / 1M output default
                $cost = (($promptTokens / 1000.0) * $promptRate) + (($completionTokens / 1000.0) * $completionRate);
            }


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
            Log::error('OpenRouter generation failed: '.$e->getMessage());
            throw $e;
        }
    }

    public function getConfig(): array
    {
        return [
            'base_url' => 'https://openrouter.ai/api/v1',
        ];
    }
}
