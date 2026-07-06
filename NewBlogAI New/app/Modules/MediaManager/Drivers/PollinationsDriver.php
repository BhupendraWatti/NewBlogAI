<?php

namespace App\Modules\MediaManager\Drivers;

use App\Modules\MediaManager\Contracts\ImageGeneratorInterface;
use Illuminate\Support\Facades\Log;

class PollinationsDriver implements ImageGeneratorInterface
{
    /**
     * Generate or fetch an image using Pollinations AI.
     *
     * @return array{url: string, metadata: array}
     */
    public function generate(string $prompt, array $options = []): array
    {
        Log::info("PollinationsDriver: Generating image with prompt: '{$prompt}'");

        $width = $options['width'] ?? 1024;
        $height = $options['height'] ?? 1024;
        $seed = $options['seed'] ?? rand(1, 100000);
        $model = $options['model'] ?? 'flux';

        $params = [
            'width' => $width,
            'height' => $height,
            'seed' => $seed,
            'model' => $model,
            'nologo' => 'true',
        ];

        // Format the URL. Note: URL-encode the prompt.
        $encodedPrompt = urlencode($prompt);
        $url = "https://image.pollinations.ai/prompt/{$encodedPrompt}?".http_build_query($params);

        return [
            'url' => $url,
            'metadata' => [
                'driver' => 'pollinations',
                'prompt' => $prompt,
                'width' => $width,
                'height' => $height,
                'seed' => $seed,
                'model' => $model,
            ],
        ];
    }
}
