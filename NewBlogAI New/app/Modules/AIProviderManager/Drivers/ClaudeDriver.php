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
            // -- Structured Output: tool-use extraction -------------------------
            // Anthropic has no response_format field. Structured output is
            // achieved via tool-use: define a synthetic tool whose input_schema
            // is the desired JSON schema,  tool_choice to that tool.
            // The API returns the structured data as a parsed object inside
            // content[].input, which we re-serialize to a JSON string so the
            // standard 'text' return key works unchanged for all callers.
            $useStructuredOutput = ! empty($options['json_mode']) || ! empty($options['json_schema']);

            $payload = [
                'model'       => $model,
                'messages'    => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens'  => $options['max_tokens'] ?? 2000,
            ];

            if ($useStructuredOutput) {
                // Build input_schema: use caller schema when provided, else
                // fall back to a generic object so JSON is still guaranteed.
                $inputSchema = ! empty($options['json_schema']['schema'])
                    ? $options['json_schema']['schema']
                    : ['type' => 'object', 'additionalProperties' => true];

                $toolName = ! empty($options['json_schema']['name'])
                    ? $options['json_schema']['name']
                    : 'extract_structured_output';

                $payload['tools'] = [
                    [
                        'name'         => $toolName,
                        'description'  => 'Return a structured JSON response.',
                        'input_schema' => $inputSchema,
                    ],
                ];
                $payload['tool_choice'] = ['type' => 'tool', 'name' => $toolName];
            }
            // -- End Structured Output -------------------------------------------

            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])
                ->timeout($options['timeout'] ?? 90)
                ->post('https://api.anthropic.com/v1/messages', $payload);

            if (! $response->successful()) {
                throw new \RuntimeException("Claude API error: Status {$response->status()} - ".$response->body());
            }

            $data = $response->json();

            // -- Structured Output: extract from tool-use response ---------
            if ($useStructuredOutput) {
                // Tool-use: content block type is 'tool_use'; its 'input' is
                // already a decoded PHP array — re-encode to JSON string.
                $toolBlock = collect($data['content'] ?? [])
                    ->firstWhere('type', 'tool_use');
                $text = $toolBlock
                    ? json_encode($toolBlock['input'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : '';
            } else {
                // Standard text response path (no structured output)
                $text = $data['content'][0]['text'] ?? '';
            }
            $usage = $data['usage'] ?? [];
            $uncachedTokens = (int) ($usage['input_tokens'] ?? 0);
            $cacheReadTokens = (int) ($usage['cache_read_input_tokens'] ?? 0);
            $cacheCreation = (int) ($usage['cache_creation_input_tokens'] ?? 0);
            $cacheCreation1h = (int) ($usage['cache_creation']['ephemeral_1h_input_tokens'] ?? 0);
            $cacheCreation5m = (int) ($usage['cache_creation']['ephemeral_5m_input_tokens'] ?? max(0, $cacheCreation - $cacheCreation1h));
            $promptTokens = $uncachedTokens + $cacheReadTokens + $cacheCreation;
            $completionTokens = $usage['output_tokens'] ?? 0;
            $totalTokens = $promptTokens + $completionTokens;

            // ponytail: unknown Claude IDs use Sonnet rates; use a versioned catalog if arbitrary models are enabled.
            [$promptRate, $completionRate] = match (true) {
                str_contains($model, 'fable-5') => [0.01000, 0.05000],
                str_contains($model, 'sonnet-5') => [0.00200, 0.01000],
                str_contains($model, 'opus-5'), preg_match('/opus-4-[567]/', $model) === 1 => [0.00500, 0.02500],
                str_contains($model, 'opus-4'), str_contains($model, 'opus-3') => [0.01500, 0.07500],
                str_contains($model, 'haiku-4-5') => [0.00100, 0.00500],
                str_contains($model, '3-5-haiku') => [0.00080, 0.00400],
                str_contains($model, '3-haiku') => [0.00025, 0.00125],
                default => [0.00300, 0.01500],
            };
            $cost = (($uncachedTokens / 1000.0) * $promptRate)
                + (($cacheCreation5m / 1000.0) * $promptRate * 1.25)
                + (($cacheCreation1h / 1000.0) * $promptRate * 2)
                + (($cacheReadTokens / 1000.0) * $promptRate * 0.1)
                + (($completionTokens / 1000.0) * $completionRate);


            $limit     = $response->header('anthropic-ratelimit-tokens-limit') ?: ($response->header('anthropic-ratelimit-requests-limit') ?: null);
            $remaining = $response->header('anthropic-ratelimit-tokens-remaining') ?: ($response->header('anthropic-ratelimit-requests-remaining') ?: null);
            $reset     = $response->header('anthropic-ratelimit-tokens-reset') ?: ($response->header('anthropic-ratelimit-requests-reset') ?: null);

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
