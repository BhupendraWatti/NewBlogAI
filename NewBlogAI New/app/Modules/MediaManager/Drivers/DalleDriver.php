<?php

namespace App\Modules\MediaManager\Drivers;

use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\MediaManager\Contracts\ImageGeneratorInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DalleDriver implements ImageGeneratorInterface
{
    /**
     * Generate an image using OpenAI DALL-E.
     *
     * @return array{url: string, metadata: array}
     */
    public function generate(string $prompt, array $options = []): array
    {
        Log::info("DalleDriver: Generating image with prompt: '{$prompt}'");

        // 1. Resolve API key
        $apiKey = config('services.openai.key') ?? env('OPENAI_API_KEY');

        if (empty($apiKey)) {
            // Check AIProvider table for openai
            try {
                $provider = AIProvider::where('provider_key', 'openai')->first();
                if ($provider && ! empty($provider->api_key)) {
                    $apiKey = $provider->api_key;
                }
            } catch (\Exception $e) {
                // Ignore if model or database is not ready
            }
        }

        if (empty($apiKey)) {
            throw new \RuntimeException('OpenAI API Key is not configured for DALL-E.');
        }

        // Support mock/test key for sandbox/testing environment
        if (str_starts_with($apiKey, 'sk-proj-my-openai-test-key') || str_contains($apiKey, 'mock')) {
            Log::info('DalleDriver: Using mock mode.');

            return [
                'url' => 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe',
                'metadata' => [
                    'driver' => 'dalle',
                    'prompt' => $prompt,
                    'model' => $options['model'] ?? 'dall-e-3',
                    'size' => $options['size'] ?? '1024x1024',
                    'mock' => true,
                ],
            ];
        }

        $model = $options['model'] ?? 'dall-e-3';
        $size = $options['size'] ?? '1024x1024';
        $quality = $options['quality'] ?? 'standard';

        $response = Http::withToken($apiKey)
            ->timeout($options['timeout'] ?? 60)
            ->post('https://api.openai.com/v1/images/generations', [
                'model' => $model,
                'prompt' => $prompt,
                'n' => 1,
                'size' => $size,
                'quality' => $quality,
                'response_format' => 'url',
            ]);

        if ($response->failed()) {
            Log::error('DALL-E API call failed: '.$response->body());
            throw new \RuntimeException('DALL-E image generation failed: '.$response->reason());
        }

        $data = $response->json();
        $imageUrl = $data['data'][0]['url'] ?? null;

        if (! $imageUrl) {
            throw new \RuntimeException('DALL-E API response did not contain an image URL.');
        }

        return [
            'url' => $imageUrl,
            'metadata' => [
                'driver' => 'dalle',
                'prompt' => $prompt,
                'model' => $model,
                'size' => $size,
                'quality' => $quality,
                'revised_prompt' => $data['data'][0]['revised_prompt'] ?? null,
            ],
        ];
    }
}
