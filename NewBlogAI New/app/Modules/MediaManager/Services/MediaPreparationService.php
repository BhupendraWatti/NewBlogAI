<?php

declare(strict_types=1);

namespace App\Modules\MediaManager\Services;

use App\Modules\ContentPipeline\Contracts\MediaPreparatorInterface;
use App\Modules\ContentPipeline\DTOs\PipelineContext;
use App\Modules\MediaManager\Services\ImageGeneratorService;
use App\Modules\MediaManager\Services\ContentPostProcessor;
use App\Modules\SubscriptionManager\Services\EntitlementService;
use Illuminate\Support\Facades\Log;

class MediaPreparationService implements MediaPreparatorInterface
{
    public function __construct(
        protected ImageGeneratorService $imageGeneratorService,
        protected ContentPostProcessor $contentPostProcessor,
        protected EntitlementService $entitlementService
    ) {}

    /**
     * Process the media preparation stage of the content pipeline.
     */
    public function handle(PipelineContext $context): PipelineContext
    {
        if ($context->hasErrors()) {
            return $context;
        }

        try {
            Log::info('MediaPreparationService: Starting media preparation.');

            // 0. Enforce storage quota before generating any images.
            $site = $context->pipeline?->site;
            if ($site) {
                $this->entitlementService->assertStorageWithinLimit($site);
            }

            $markdown = $context->generatedContent;
            if (empty($markdown)) {
                Log::warning('MediaPreparationService: No content to process.');
                return $context;
            }

            // 1. Convert markdown to HTML (reusing ContentPostProcessor's converter)
            $htmlContent = $this->contentPostProcessor->convertMarkdownToHtml($markdown);

            $tempHtml = $htmlContent;
            $blockSpecs = [];
            $inlineSpecs = [];
            
            // Standalone block comments: <p>&lt;!-- image-placeholder: ... --&gt;</p>
            $patternBlock = '/<p>\s*&lt;!--\s*image-placeholder:\s*(.*?)\s*--&gt;\s*<\/p>/i';
            // Inline comments: &lt;!-- image-placeholder: ... --&gt;
            $patternInline = '/&lt;!--\s*image-placeholder:\s*(.*?)\s*--&gt;/i';

            // Find block matches first
            preg_match_all($patternBlock, $tempHtml, $blockMatches, PREG_SET_ORDER);
            foreach ($blockMatches as $match) {
                $rawContent = htmlspecialchars_decode(trim($match[1]), ENT_QUOTES);
                $parsed = $this->parsePlaceholder($rawContent);
                $blockSpecs[] = [
                    'prompt' => $parsed['prompt'],
                    'alt' => $parsed['alt'],
                    'caption' => $parsed['caption'],
                    'status' => 'pending',
                ];
            }

            // Remove block matches to prevent double matching
            $tempHtml = preg_replace($patternBlock, '', $tempHtml);

            // Find inline matches
            preg_match_all($patternInline, $tempHtml, $inlineMatches, PREG_SET_ORDER);
            foreach ($inlineMatches as $match) {
                $rawContent = htmlspecialchars_decode(trim($match[1]), ENT_QUOTES);
                $parsed = $this->parsePlaceholder($rawContent);
                $inlineSpecs[] = [
                    'prompt' => $parsed['prompt'],
                    'alt' => $parsed['alt'],
                    'caption' => $parsed['caption'],
                    'status' => 'pending',
                ];
            }

            // Combine block and inline specs
            $allInlineSpecs = array_merge($blockSpecs, $inlineSpecs);

            // Structure the featured image prompt and attributes
            $pipeline = $context->pipeline;
            $topic = $pipeline?->topic;
            $topicName = $context->resolvedTopic ?? ($topic ? $topic->name : 'blog post');
            $imagePrompt = "A professional and high-quality featured image representing: {$topicName}";
            $title = $context->title ?? $topicName;

            // Structure all media specifications in $context->metadata['media_specs'] before they are executed.
            $mediaSpecs = [
                'images' => [
                    'featured' => [
                        'prompt' => $imagePrompt,
                        'alt' => $title,
                        'caption' => $topicName,
                        'status' => 'pending',
                    ],
                    'inline' => $allInlineSpecs,
                ],
                'videos' => [],        // Hook for future Video requests
                'audio' => [],         // Hook for future Audio requests
                'infographics' => [],  // Hook for future Infographics requests
            ];

            // 2. Execute featured image generation
            try {
                Log::info("Generating featured image with prompt: '{$imagePrompt}'");
                $options = [
                    'alt'     => $title,
                    'caption' => $topicName,
                    'site_id' => $site?->id,
                ];
                $mediaItem = $this->imageGeneratorService->generateAndStore($imagePrompt, $options, null);

                // Add to context mediaItems
                $context->addMediaItem([
                    'id' => $mediaItem->id,
                    'url' => $mediaItem->url,
                    'filepath' => $mediaItem->filepath,
                    'filename' => $mediaItem->filename,
                    'prompt' => $imagePrompt,
                ]);

                // Prepend the generated image at the beginning of the HTML content
                $imageHtml = sprintf(
                    '<div class="post-featured-image" style="margin-bottom: 20px;"><img src="%s" alt="%s" class="img-fluid" style="max-width: 100%%; height: auto; border-radius: 8px;" /></div>' . "\n\n",
                    e($mediaItem->url),
                    e($title)
                );
                $htmlContent = $imageHtml . $htmlContent;

                // Save the featured image ID and url in context metadata
                $context->metadata['featured_image_id'] = $mediaItem->id;
                $context->metadata['featured_image_url'] = $mediaItem->url;

                // Update status in mediaSpecs
                $mediaSpecs['images']['featured']['status'] = 'generated';
            } catch (\Exception $e) {
                if ($e->getMessage() === 'Image generation is disabled in system settings.') {
                    Log::info("Skipping featured image: " . $e->getMessage());
                    $mediaSpecs['images']['featured']['status'] = 'disabled';
                } else {
                    Log::error("Failed to generate featured image: " . $e->getMessage());
                    $mediaSpecs['images']['featured']['status'] = 'failed';
                }
            }

            // 3. Execute inline image generations
            $generatedMediaItems = []; // maps overall index -> MediaItem or null
            foreach ($mediaSpecs['images']['inline'] as $index => &$spec) {
                try {
                    Log::info("Generating inline image for prompt: '{$spec['prompt']}'");
                    $options = [
                        'alt'     => $spec['alt'],
                        'caption' => $spec['caption'],
                        'site_id' => $site?->id,
                    ];
                    $mediaItem = $this->imageGeneratorService->generateAndStore($spec['prompt'], $options, null);

                    // Add to context mediaItems
                    $context->addMediaItem([
                        'id' => $mediaItem->id,
                        'url' => $mediaItem->url,
                        'filepath' => $mediaItem->filepath,
                        'filename' => $mediaItem->filename,
                        'prompt' => $spec['prompt'],
                    ]);

                    $spec['status'] = 'generated';
                    $generatedMediaItems[$index] = $mediaItem;
                } catch (\Exception $e) {
                    if ($e->getMessage() === 'Image generation is disabled in system settings.') {
                        Log::info("Skipping inline image: " . $e->getMessage());
                        $spec['status'] = 'disabled';
                    } else {
                        Log::error("Failed to generate inline image for prompt '{$spec['prompt']}': " . $e->getMessage());
                        $spec['status'] = 'failed';
                    }
                    $generatedMediaItems[$index] = null;
                }
            }
            unset($spec);

            // Assign the final mediaSpecs to context
            $context->metadata['media_specs'] = $mediaSpecs;

            // 4. Scan and replace placeholders with HTML <figure> elements in $htmlContent
            $numBlocks = count($blockSpecs);
            $blockIndex = 0;
            $inlineIndex = $numBlocks;

            $callbackBlock = function (array $matches) use (&$blockIndex, $generatedMediaItems) {
                $rawContent = htmlspecialchars_decode(trim($matches[1]), ENT_QUOTES);
                $parsed = $this->parsePlaceholder($rawContent);

                $mediaItem = $generatedMediaItems[$blockIndex] ?? null;
                $blockIndex++;

                if ($mediaItem) {
                    return sprintf(
                        '<figure class="wp-block-image size-large" style="margin: 20px 0;"><img src="%s" alt="%s" class="img-fluid" style="max-width: 100%%; height: auto; border-radius: 8px;" /><figcaption style="font-style: italic; text-align: center; font-size: 0.9em; margin-top: 5px;">%s</figcaption></figure>',
                        e($mediaItem->url),
                        e($parsed['alt']),
                        e($parsed['caption'])
                    );
                } else {
                    return '<!-- image-placeholder-failed: ' . e($parsed['prompt']) . ' -->';
                }
            };

            $callbackInline = function (array $matches) use (&$inlineIndex, $generatedMediaItems) {
                $rawContent = htmlspecialchars_decode(trim($matches[1]), ENT_QUOTES);
                $parsed = $this->parsePlaceholder($rawContent);

                $mediaItem = $generatedMediaItems[$inlineIndex] ?? null;
                $inlineIndex++;

                if ($mediaItem) {
                    return sprintf(
                        '<figure class="wp-block-image size-large" style="margin: 20px 0;"><img src="%s" alt="%s" class="img-fluid" style="max-width: 100%%; height: auto; border-radius: 8px;" /><figcaption style="font-style: italic; text-align: center; font-size: 0.9em; margin-top: 5px;">%s</figcaption></figure>',
                        e($mediaItem->url),
                        e($parsed['alt']),
                        e($parsed['caption'])
                    );
                } else {
                    return '<!-- image-placeholder-failed: ' . e($parsed['prompt']) . ' -->';
                }
            };

            // Replace block comments
            $htmlContent = preg_replace_callback($patternBlock, $callbackBlock, $htmlContent);

            // Replace inline comments
            $htmlContent = preg_replace_callback($patternInline, $callbackInline, $htmlContent);

            // Set processed_at to prevent downstream re-processing
            $context->metadata['processed_at'] = now()->toIso8601String();

            // Save the resulting HTML in context
            $context->generatedContent = $htmlContent;

            Log::info('MediaPreparationService: Media preparation completed successfully.');

        } catch (\Exception $e) {
            Log::error('MediaPreparationService failed: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            $context->addError('media_preparation', $e->getMessage());
            throw $e;
        }

        return $context;
    }

    /**
     * Parse the raw placeholder content to extract prompt, alt, and caption.
     */
    protected function parsePlaceholder(string $rawContent): array
    {
        $attributes = [];
        preg_match_all('/(\w+)\s*=\s*["\']([^"\']*)["\']/', $rawContent, $matches, PREG_SET_ORDER);

        if (! empty($matches)) {
            foreach ($matches as $match) {
                $attributes[$match[1]] = $match[2];
            }
        }

        if (empty($attributes['prompt'])) {
            $prompt = trim($rawContent);

            return [
                'prompt' => $prompt,
                'alt' => $prompt,
                'caption' => $prompt,
            ];
        }

        return [
            'prompt' => $attributes['prompt'],
            'alt' => $attributes['alt'] ?? $attributes['prompt'],
            'caption' => $attributes['caption'] ?? $attributes['prompt'],
        ];
    }
}
