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
            $response = Http::timeout(90)->post($url, [
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
        $timeout = $options['timeout'] ?? 180;

        return $this->executeWithRetryAndLog($apiKey, $prompt, $model, $options, function ($key, $p, $m, $opts) use ($timeout) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent?key={$key}";
            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $p],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature'     => $opts['temperature'] ?? 0.7,
                    'maxOutputTokens' => $opts['max_tokens'] ?? 2048,
                ],
            ];

            if (array_key_exists('thinking_budget', $opts) && $opts['thinking_budget'] !== null) {
                $payload['generationConfig']['thinkingConfig'] = [
                    'thinkingBudget' => (int) $opts['thinking_budget'],
                ];
            }

            // ── Structured Output: JSON Mode ──────────────────────────────────
            // When either json_mode or json_schema is requested, set the MIME
            // type so Gemini guarantees a well-formed JSON response every time.
            if (! empty($opts['json_mode']) || ! empty($opts['json_schema'])) {
                $payload['generationConfig']['responseMimeType'] = 'application/json';
            }

            // When a full JSON Schema is provided, pass it as responseSchema so
            // Gemini constrains its output to exactly the declared shape.
            // Gemini accepts a JSON Schema object (OpenAPI 3.0 subset).
            if (! empty($opts['json_schema']['schema'])) {
                $payload['generationConfig']['responseSchema'] = $this->cleanSchemaForGemini($opts['json_schema']['schema']);
            }
            // ── End Structured Output ─────────────────────────────────────────

            if (! empty($opts['tools'])) {
                $payload['tools'] = $opts['tools'];
            }

            return Http::timeout($timeout)->post($url, $payload);
        });
    }

    /**
     * Clean JSON Schema array recursively to make it fully compatible with Google Gemini API
     * (removes additionalProperties, strict, name, and resolves union types like ['string', 'null']).
     */
    private function cleanSchemaForGemini(array $schema): array
    {
        unset($schema['additionalProperties']);
        unset($schema['strict']);
        unset($schema['name']);

        if (isset($schema['type'])) {
            if (is_array($schema['type'])) {
                $types = $schema['type'];
                $hasNull = in_array('null', $types, true);
                $nonNullTypes = array_values(array_filter($types, fn($t) => $t !== 'null'));
                $schema['type'] = $nonNullTypes[0] ?? 'string';
                if ($hasNull) {
                    $schema['nullable'] = true;
                }
            }
        }

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $key => $prop) {
                if (is_array($prop)) {
                    $schema['properties'][$key] = $this->cleanSchemaForGemini($prop);
                }
            }
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->cleanSchemaForGemini($schema['items']);
        }

        return $schema;
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
                    Log::info("Gemini retry backoff: waiting {$delay}s before attempt #{$retry}");
                    sleep($delay);
                }

                $response = $apiCall($apiKey, $prompt, $model, $options);
                $latency = (int) ((microtime(true) - $startTime) * 1000);

                if ($response->status() === 429 && $retry < $maxRetries) {
                    Log::warning("Gemini rate limit (429) hit, retrying...", [
                        'attempt' => $retry + 1,
                        'latency_ms' => $latency
                    ]);
                    continue;
                }

                if (!$response->successful()) {
                    throw new \RuntimeException("Gemini API error: Status {$response->status()} - " . $response->body());
                }

                // ── HTML body guard ──────────────────────────────────────────
                // When Google Search grounding hits a CDN/quota error, Gemini
                // occasionally returns HTTP 200 with an HTML error document as
                // the response body instead of JSON. The successful() check
                // above only validates the HTTP status code, so we must also
                // sniff the body and Content-Type header before attempting to
                // decode. An HTML body flowing into parseCandidates() produces:
                //   "Unexpected token '<', '<html><h…' is not valid JSON"
                $contentType = $response->header('Content-Type') ?? '';
                $bodyPreview = substr($response->body(), 0, 5);
                if (
                    str_contains($contentType, 'text/html')
                    || $bodyPreview === '<html'
                    || $bodyPreview === '<!DOC'
                    || ltrim($response->body()) === ''
                ) {
                    throw new \RuntimeException(
                        'Gemini returned an HTML error body instead of JSON (grounding quota/CDN error). '
                        . 'Status: ' . $response->status() . '. '
                        . 'Content-Type: ' . $contentType . '. '
                        . 'Preview: ' . mb_substr($response->body(), 0, 120)
                    );
                }
                // ── End HTML body guard ───────────────────────────────────────

                $data = $response->json();
                $candidate = $data['candidates'][0] ?? [];
                $finishReason = $candidate['finishReason'] ?? 'STOP';

                if ($finishReason !== 'STOP' && $finishReason !== 'MAX_TOKENS') {
                    Log::warning("Gemini generation finished with non-standard reason: {$finishReason}", [
                        'finish_reason' => $finishReason,
                        'safety_ratings' => $candidate['safetyRatings'] ?? [],
                    ]);
                }

                $text = $candidate['content']['parts'][0]['text'] ?? '';

                $usage = $data['usageMetadata'] ?? [];
                $promptTokens = $usage['promptTokenCount'] ?? 0;
                $completionTokens = $usage['candidatesTokenCount'] ?? 0;
                $totalTokens = $usage['totalTokenCount'] ?? 0;

                if (str_contains($model, 'pro')) {
                    $promptRate     = 0.00125;  // $1.25 / 1M input
                    $completionRate = 0.00500;  // $5.00 / 1M output
                } else {
                    // gemini-2.5-flash / gemini-1.5-flash
                    $promptRate     = 0.000075; // $0.075 / 1M input
                    $completionRate = 0.000300; // $0.300 / 1M output
                }
                $cost = (($promptTokens / 1000.0) * $promptRate) + (($completionTokens / 1000.0) * $completionRate);


                $limit     = $response->header('x-ratelimit-limit-requests') ?: $response->header('x-ratelimit-limit-tokens');
                $remaining = $response->header('x-ratelimit-remaining-requests') ?: $response->header('x-ratelimit-remaining-tokens');
                $reset     = $response->header('x-ratelimit-reset-requests') ?: $response->header('x-ratelimit-reset-tokens');

                // Structured logging for task, model, input tokens, output tokens, latency, and retry count
                Log::info('AI API Call Log', [
                    'task' => $options['task'] ?? 'generation',
                    'provider' => 'gemini',
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
                        'limit' => $limit ?: null,
                        'remaining' => $remaining ?: null,
                        'reset' => $reset ?: null,
                    ],
                ];

            } catch (\Exception $e) {
                $lastException = $e;
                $latency = (int) ((microtime(true) - $startTime) * 1000);

                if ($retry < $maxRetries && (str_contains($e->getMessage(), '429') || str_contains(strtolower($e->getMessage()), 'rate limit'))) {
                    Log::warning("Gemini rate limit hit (exception), retrying...", [
                        'attempt' => $retry + 1,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }

                Log::error('AI API Call Failed', [
                    'task' => $options['task'] ?? 'generation',
                    'provider' => 'gemini',
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
        throw new \RuntimeException("Gemini generation failed after max retries.");
    }

    public function getConfig(): array
    {
        return [
            'base_url' => 'https://generativelanguage.googleapis.com',
        ];
    }
}
