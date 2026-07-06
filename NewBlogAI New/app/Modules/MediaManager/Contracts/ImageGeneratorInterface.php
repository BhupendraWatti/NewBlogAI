<?php

namespace App\Modules\MediaManager\Contracts;

interface ImageGeneratorInterface
{
    /**
     * Generate or fetch an image based on the prompt.
     *
     * @return array{url: string, metadata: array}
     */
    public function generate(string $prompt, array $options = []): array;
}
