<?php

namespace App\Modules\MediaManager\Services;

use App\Modules\MediaManager\Contracts\ImageGeneratorInterface;
use App\Modules\MediaManager\Drivers\DalleDriver;
use App\Modules\MediaManager\Drivers\PollinationsDriver;
use App\Modules\MediaManager\Drivers\UnsplashDriver;
use App\Modules\MediaManager\Models\MediaItem;
use App\Modules\SystemSettings\Services\SystemSettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ImageGeneratorService
{
    public function __construct(
        protected SystemSettingsService $settingsService
    ) {}

    /**
     * Get the driver instance by name.
     */
    public function getDriver(string $driverName): ImageGeneratorInterface
    {
        return match (strtolower($driverName)) {
            'pollinations' => new PollinationsDriver,
            'unsplash' => new UnsplashDriver,
            'dalle', 'dall-e' => new DalleDriver,
            default => throw new InvalidArgumentException("Unsupported image generator driver: {$driverName}"),
        };
    }

    /**
     * Generate an image, download it locally, and store a MediaItem record.
     */
    public function generateAndStore(string $prompt, array $options = [], ?int $generatedContentId = null): MediaItem
    {
        // 1. Determine driver
        $driverName = $options['driver'] ?? $this->settingsService->get('image_generator_driver', 'pollinations');
        $driver = $this->getDriver($driverName);

        // 2. Generate image
        try {
            $result = $driver->generate($prompt, $options);
            $remoteUrl = $result['url'];
            $metadata = $result['metadata'] ?? [];
        } catch (\Exception $e) {
            Log::error("Image generation failed with driver '{$driverName}': ".$e->getMessage());
            throw new \RuntimeException('Image generation failed: '.$e->getMessage(), 0, $e);
        }

        // 3. Download and save locally
        $filename = null;
        $filepath = null;
        $url = $remoteUrl;

        try {
            Log::info("Downloading generated image from: {$remoteUrl}");

            // Perform the GET download directly (many AI endpoints do not support HEAD requests)
            $response = Http::timeout(30)->get($remoteUrl);

            if ($response->successful()) {
                // Check Content-Length header if present
                $contentLength = (int) $response->header('Content-Length');
                if ($contentLength > 15 * 1024 * 1024) {
                    throw new \RuntimeException("Generated image size ({$contentLength} bytes) exceeds the 15MB safety limit.");
                }

                $imageContents = $response->body();

                // Double check memory length of string to protect memory bounds
                if (strlen($imageContents) > 15 * 1024 * 1024) {
                    throw new \RuntimeException('Downloaded image body size exceeds the 15MB safety limit.');
                }

                // 3. Inspect binary signature (finfo) to verify the file is a genuine image
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->buffer($imageContents);

                $allowedMimeTypes = [
                    'image/jpeg' => 'jpg',
                    'image/jpg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    'image/gif' => 'gif',
                ];

                if (! array_key_exists($mimeType, $allowedMimeTypes)) {
                    throw new \RuntimeException("Downloaded file mime type '{$mimeType}' is not an allowed image format.");
                }

                $extension = $allowedMimeTypes[$mimeType];
                $filename = uniqid('img_', true).'.'.$extension;
                $filepath = "media/{$filename}";

                // Store on public disk
                Storage::disk('public')->put($filepath, $imageContents);
                $url = Storage::disk('public')->url($filepath);

                Log::info("Successfully stored image locally at: {$filepath} (Public URL: {$url})");
            } else {
                Log::warning("Failed to download image (Status {$response->status()}). Falling back to remote URL.");
            }
        } catch (\Exception $e) {
            Log::warning("Error downloading image safely: {$e->getMessage()}. Falling back to remote URL.");
        }

        // 4. Save to Database
        return MediaItem::create([
            'generated_content_id' => $generatedContentId,
            'filename' => $filename,
            'filepath' => $filepath,
            'url' => $url,
            'driver' => $driverName,
            'prompt' => $prompt,
            'metadata' => $metadata,
        ]);
    }
}
