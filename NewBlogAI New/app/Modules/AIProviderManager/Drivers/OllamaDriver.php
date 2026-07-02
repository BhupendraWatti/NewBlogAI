<?php

namespace App\Modules\AIProviderManager\Drivers;

use App\Modules\AIProviderManager\Contracts\AIProviderClientInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaDriver implements AIProviderClientInterface
{
    public function testConnection(string $apiKey, ?string $model = null): bool
    {
        $host = $apiKey ?: 'http://localhost:11434';
        $model = $model ?: 'llama3';

        try {
            $response = Http::timeout(10)->post("{$host}/api/generate", [
                'model' => $model,
                'prompt' => 'ping',
                'stream' => false,
                'options' => [
                    'num_predict' => 5
                ]
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning("Ollama test connection failed with status {$response->status()}: " . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error("Ollama test connection exception: " . $e->getMessage());
            return false;
        }
    }

    public function generate(string $apiKey, string $prompt, ?string $model = null, array $options = []): array
    {
        $host = $apiKey ?: 'http://localhost:11434';
        $model = $model ?: 'llama3';

        try {
            $response = Http::timeout($options['timeout'] ?? 60)->post("{$host}/api/generate", [
                'model'  => $model,
                'prompt' => $prompt,
                'stream' => false,
                'options' => [
                    'temperature' => $options['temperature'] ?? 0.7,
                    'num_predict' => $options['max_tokens'] ?? 2048,
                ]
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException("Ollama API error: Status {$response->status()} - " . $response->body());
            }

            $data = $response->json();
            $text = $data['response'] ?? '';
            
            // Ollama outputs token counts directly
            $promptTokens = $data['prompt_eval_count'] ?? 0;
            $completionTokens = $data['eval_count'] ?? 0;
            $totalTokens = $promptTokens + $completionTokens;

            return [
                'text'              => $text,
                'prompt_tokens'     => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens'      => $totalTokens,
                'estimated_cost'    => 0.0, // Local processing is free!
                'raw_response'      => $data,
            ];

        } catch (\Exception $e) {
            Log::error("Ollama generation failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function getConfig(): array
    {
        return [
            'base_url' => 'http://localhost:11434',
        ];
    }
}
