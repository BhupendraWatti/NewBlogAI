<?php

namespace App\Modules\MediaManager\Drivers;

use App\Modules\MediaManager\Contracts\ImageGeneratorInterface;
use App\Modules\SystemSettings\Services\SystemSettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UnsplashDriver implements ImageGeneratorInterface
{
    /**
     * Generate (fetch) a random image from Unsplash based on the query/prompt.
     *
     * @return array{url: string, metadata: array}
     */
    public function generate(string $prompt, array $options = []): array
    {
        Log::info("UnsplashDriver: Searching random photo for query: '{$prompt}'");

        // Retrieve Unsplash API key
        $accessKey = config('services.unsplash.access_key')
            ?? config('services.unsplash.key')
            ?? env('UNSPLASH_ACCESS_KEY');

        if (empty($accessKey)) {
            // Check SystemSettingsService if available as a fallback
            try {
                $settingsService = app(SystemSettingsService::class);
                $accessKey = $settingsService->get('unsplash_access_key');
            } catch (\Exception $e) {
                // Ignore if settings service not available/error
            }
        }

        if (empty($accessKey)) {
            throw new \RuntimeException('Unsplash Access Key (client_id) is not configured.');
        }

        $orientation = $options['orientation'] ?? 'landscape';

        $response = Http::timeout(10)->withHeaders([
            'Authorization' => 'Client-ID '.$accessKey,
            'Accept-Version' => 'v1',
        ])->get('https://api.unsplash.com/photos/random', [
            'query' => $prompt,
            'orientation' => $orientation,
        ]);

        if ($response->failed()) {
            Log::error('Unsplash API call failed: '.$response->body());
            throw new \RuntimeException('Unsplash image retrieval failed: '.$response->reason());
        }

        $data = $response->json();
        $imageUrl = $data['urls']['regular'] ?? $data['urls']['full'] ?? null;

        if (! $imageUrl) {
            throw new \RuntimeException('Unsplash API response did not contain an image URL.');
        }

        return [
            'url' => $imageUrl,
            'metadata' => [
                'driver' => 'unsplash',
                'prompt' => $prompt,
                'photo_id' => $data['id'] ?? null,
                'author' => $data['user']['name'] ?? null,
                'author_username' => $data['user']['username'] ?? null,
                'unsplash_url' => $data['links']['html'] ?? null,
                'description' => $data['description'] ?? $data['alt_description'] ?? null,
            ],
        ];
    }
}
