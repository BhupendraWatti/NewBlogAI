<?php

namespace App\Modules\AIProviderManager\Drivers;

use App\Modules\AIProviderManager\Contracts\AIProviderClientInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqDriver implements AIProviderClientInterface
{
    public function testConnection(string $apiKey, ?string $model = null): bool
    {
        $model = $this->resolveModel($model);

        try {
            $response = Http::withToken($apiKey)
                ->timeout(90)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => 'ping'],
                    ],
                    'max_tokens' => 5,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning("Groq test connection failed with status {$response->status()}: ".$response->body());

            return false;
        } catch (\Exception $e) {
            Log::error('Groq test connection exception: '.$e->getMessage());

            return false;
        }
    }

    public function generate(string $apiKey, string $prompt, ?string $model = null, array $options = []): array
    {
        $model = $this->resolveModel($model);
        $timeout = $options['timeout'] ?? 120;

        return $this->executeWithRetryAndLog($apiKey, $prompt, $model, $options, function ($key, $p, $m, $opts) use ($timeout) {
            // -- Structured Output: json_object mode -----------------------------
            // Groq supports response_format: json_object (OpenAI-compatible).
            // Full json_schema (Structured Outputs) is NOT supported by Groq;
            // we fall back to json_object for either key so JSON is guaranteed.
            $payload = [
                'model'       => $m,
                'messages'    => [
                    ['role' => 'user', 'content' => $p],
                ],
                'temperature' => $opts['temperature'] ?? 0.7,
                'max_tokens'  => $opts['max_tokens'] ?? 2000,
            ];

            if (! empty($opts['json_mode']) || ! empty($opts['json_schema'])) {
                $payload['response_format'] = ['type' => 'json_object'];
            }
            // -- End Structured Output -------------------------------------------

            return Http::withToken($key)
                ->timeout($timeout)
                ->post('https://api.groq.com/openai/v1/chat/completions', $payload);
        });
    }

    protected function executeWithRetryAndLog(
        string $apiKey,
        string $prompt,
        string $model,
        array $options,
        callable $apiCall
    ): array {
        $maxRetries = max(0, (int) ($options['max_retries'] ?? 3));
        $lastException = null;

        for ($retry = 0; $retry <= $maxRetries; $retry++) {
            $startTime = microtime(true);
            try {
                if ($retry > 0) {
                    $delay = pow(2, $retry); // 2s, 4s, 8s
                    Log::info("Groq retry backoff: waiting {$delay}s before attempt #{$retry}");
                    sleep($delay);
                }

                $response = $apiCall($apiKey, $prompt, $model, $options);
                $latency = (int) ((microtime(true) - $startTime) * 1000);

                if ($response->status() === 429 && $retry < $maxRetries) {
                    Log::warning("Groq rate limit (429) hit, retrying...", [
                        'attempt' => $retry + 1,
                        'latency_ms' => $latency
                    ]);
                    continue;
                }

                if (!$response->successful()) {
                    throw new \RuntimeException("Groq API error: Status {$response->status()} - " . $response->body());
                }

                $data = $response->json();
                $text = $data['choices'][0]['message']['content'] ?? '';
                $usage = $data['usage'] ?? [];
                $promptTokens = $usage['prompt_tokens'] ?? 0;
                $completionTokens = $usage['completion_tokens'] ?? 0;
                $totalTokens = $usage['total_tokens'] ?? 0;

                // ponytail: unknown Groq IDs use the configured Llama 70B rate; use account billing data for custom contracts.
                [$promptRate, $cachedRate, $completionRate] = match (true) {
                    $model === 'openai/gpt-oss-120b' => [0.00015, 0.000075, 0.00060],
                    in_array($model, ['openai/gpt-oss-20b', 'openai/gpt-oss-safeguard-20b'], true) => [0.000075, 0.0000375, 0.00030],
                    $model === 'llama-3.1-8b-instant' => [0.00005, 0.00005, 0.00008],
                    $model === 'qwen/qwen3.6-27b' => [0.00060, 0.00060, 0.00300],
                    $model === 'qwen/qwen3.8-27b' => [0.00080, 0.00080, 0.00400],
                    str_contains($model, '8b') => [0.00005, 0.00005, 0.00008],
                    str_contains($model, 'mixtral') => [0.00024, 0.00024, 0.00024],
                    default => [0.00059, 0.00059, 0.00079],
                };
                $cachedTokens = min($promptTokens, (int) ($usage['prompt_tokens_details']['cached_tokens'] ?? 0));
                $cost = ((($promptTokens - $cachedTokens) / 1000.0) * $promptRate)
                    + (($cachedTokens / 1000.0) * $cachedRate)
                    + (($completionTokens / 1000.0) * $completionRate);


                $limit     = $response->header('x-ratelimit-limit-tokens') ?: ($response->header('x-ratelimit-limit-requests') ?: null);
                $remaining = $response->header('x-ratelimit-remaining-tokens') ?: ($response->header('x-ratelimit-remaining-requests') ?: null);
                $reset     = $response->header('x-ratelimit-reset-tokens') ?: ($response->header('x-ratelimit-reset-requests') ?: null);

                // Structured logging for task, model, input tokens, output tokens, latency, and retry count
                Log::info('AI API Call Log', [
                    'task' => $options['task'] ?? 'generation',
                    'provider' => 'groq',
                    'model' => $model,
                    'input_tokens' => $promptTokens,
                    'output_tokens' => $completionTokens,
                    'latency_ms' => $latency,
                    'retry_count' => $retry,
                ]);

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
                $lastException = $e;
                $latency = (int) ((microtime(true) - $startTime) * 1000);

                if ($retry < $maxRetries && (str_contains($e->getMessage(), '429') || str_contains(strtolower($e->getMessage()), 'rate limit'))) {
                    Log::warning("Groq rate limit hit (exception), retrying...", [
                        'attempt' => $retry + 1,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }

                Log::error('AI API Call Failed', [
                    'task' => $options['task'] ?? 'generation',
                    'provider' => 'groq',
                    'model' => $model,
                    'latency_ms' => $latency,
                    'retry_count' => $retry,
                    'error' => $e->getMessage()
                ]);

                throw $e;
            }
        }

        if ($lastException) {
            throw $lastException;
        }
        throw new \RuntimeException("Groq generation failed after max retries.");
    }

    private function resolveModel(?string $model): string
    {
        if (empty($model) || $model === 'llama-3.3-70b-versatile' || $model === 'llama-3.1-70b-versatile') {
            return 'openai/gpt-oss-120b';
        }

        return $model;
    }

    public function getConfig(): array
    {
        return [
            'base_url' => 'https://api.groq.com/openai/v1',
        ];
    }
}
