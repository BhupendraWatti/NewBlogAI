<?php

namespace App\Modules\AIProviderManager\Drivers;

use App\Modules\AIProviderManager\Contracts\AIProviderClientInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqDriver implements AIProviderClientInterface
{
    public function testConnection(string $apiKey, ?string $model = null): bool
    {
        $model = $model ?: 'llama-3.1-70b-versatile';

        try {
            $response = Http::withToken($apiKey)
                ->timeout(10)
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
        $model = $model ?: 'llama-3.1-70b-versatile';

        try {
            $response = Http::withToken($apiKey)
                ->timeout($options['timeout'] ?? 90)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => $options['temperature'] ?? 0.7,
                    'max_tokens' => $options['max_tokens'] ?? 2000,
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException("Groq API error: Status {$response->status()} - ".$response->body());
            }

            $data = $response->json();
            $text = $data['choices'][0]['message']['content'] ?? '';
            $usage = $data['usage'] ?? [];
            $promptTokens = $usage['prompt_tokens'] ?? 0;
            $completionTokens = $usage['completion_tokens'] ?? 0;
            $totalTokens = $usage['total_tokens'] ?? 0;

            // Groq Pricing estimation (llama-3-70b rates)
            $cost = (($promptTokens * 0.00005) + ($completionTokens * 0.00005)) / 1000;

            return [
                'text' => $text,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $totalTokens,
                'estimated_cost' => $cost,
                'raw_response' => $data,
            ];

        } catch (\Exception $e) {
            Log::error('Groq generation failed: '.$e->getMessage());
            throw $e;
        }
    }

    public function getConfig(): array
    {
        return [
            'base_url' => 'https://api.groq.com/openai/v1',
        ];
    }
}
