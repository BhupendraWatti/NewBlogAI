<?php

namespace App\Modules\MediaManager\Services;

use App\Modules\ContentGeneration\Models\GeneratedContent;
use Illuminate\Support\Facades\Log;

class ContentPostProcessor
{
    public function __construct(
        protected ImageGeneratorService $imageGeneratorService
    ) {}

    /**
     * Process the generated content: convert Markdown to HTML, generate/prepend a featured image, and update the model.
     */
    public function process(GeneratedContent $generatedContent): void
    {
        Log::info("ContentPostProcessor: Processing GeneratedContent ID {$generatedContent->id}");

        // Prevent double-invocation corruption of already-compiled HTML content
        $metadata = $generatedContent->metadata ?? [];
        if (isset($metadata['processed_at'])) {
            Log::info("ContentPostProcessor: GeneratedContent ID {$generatedContent->id} is already processed. Skipping.");

            return;
        }

        $markdown = $generatedContent->content;

        // 1. Convert markdown to HTML first so that block/inline structures are properly built
        $htmlContent = $this->convertMarkdownToHtml($markdown);

        // 2. Scans for and replaces image placeholders: &lt;!-- image-placeholder: prompt="..." alt="..." caption="..." --&gt;
        // We match standalone paragraphs with comments first, then fall back to inline comments
        $callback = function (array $matches) use ($generatedContent) {
            // HTML entity decode to restore original quotes/attributes
            $rawContent = htmlspecialchars_decode(trim($matches[1]), ENT_QUOTES);
            $parsed = $this->parsePlaceholder($rawContent);

            try {
                Log::info("Generating inline image for GeneratedContent ID {$generatedContent->id} using prompt: '{$parsed['prompt']}'");
                $mediaItem = $this->imageGeneratorService->generateAndStore($parsed['prompt'], [], $generatedContent->id);

                return sprintf(
                    '<figure class="wp-block-image size-large" style="margin: 20px 0;"><img src="%s" alt="%s" class="img-fluid" style="max-width: 100%%; height: auto; border-radius: 8px;" /><figcaption style="font-style: italic; text-align: center; font-size: 0.9em; margin-top: 5px;">%s</figcaption></figure>',
                    e($mediaItem->url),
                    e($parsed['alt']),
                    e($parsed['caption'])
                );
            } catch (\Exception $e) {
                Log::error("Failed to generate inline image for placeholder '{$rawContent}': ".$e->getMessage());

                return '<!-- image-placeholder-failed: '.e($parsed['prompt']).' -->';
            }
        };

        // Standalone block comments: <p>&lt;!-- image-placeholder: ... --&gt;</p>
        $patternBlock = '/<p>\s*&lt;!--\s*image-placeholder:\s*(.*?)\s*--&gt;\s*<\/p>/i';
        $htmlContent = preg_replace_callback($patternBlock, $callback, $htmlContent);

        // Inline comments: &lt;!-- image-placeholder: ... --&gt;
        $patternInline = '/&lt;!--\s*image-placeholder:\s*(.*?)\s*--&gt;/i';
        $htmlContent = preg_replace_callback($patternInline, $callback, $htmlContent);

        // 3. Generate a featured image
        $topicName = $generatedContent->topic?->name ?? 'blog post';
        $imagePrompt = "A professional and high-quality featured image representing: {$topicName}";

        try {
            Log::info("Generating featured image for GeneratedContent ID {$generatedContent->id} using prompt: '{$imagePrompt}'");
            // Call image generator service to create/store a MediaItem
            $mediaItem = $this->imageGeneratorService->generateAndStore($imagePrompt, [], $generatedContent->id);

            // Prepend the generated image at the beginning of the HTML content
            $imageHtml = sprintf(
                '<div class="post-featured-image" style="margin-bottom: 20px;"><img src="%s" alt="%s" class="img-fluid" style="max-width: 100%%; height: auto; border-radius: 8px;" /></div>'."\n\n",
                e($mediaItem->url),
                e($generatedContent->title)
            );
            $htmlContent = $imageHtml.$htmlContent;

            // Save the media item details in metadata
            $metadata['featured_image_id'] = $mediaItem->id;
            $metadata['featured_image_url'] = $mediaItem->url;
        } catch (\Exception $e) {
            Log::error("Failed to generate featured image for GeneratedContent ID {$generatedContent->id}: ".$e->getMessage());
            // We do not crash the pipeline if image generation fails, just proceed with HTML content
        }

        // Mark as processed to prevent double-invocation
        $metadata['processed_at'] = now()->toIso8601String();
        $generatedContent->metadata = $metadata;

        // 3. Update the GeneratedContent content field with the processed HTML
        $generatedContent->content = $htmlContent;
        $generatedContent->save();
    }

    /**
     * Parse the raw placeholder content to extract prompt, alt, and caption.
     */
    protected function parsePlaceholder(string $rawContent): array
    {
        $attributes = [];
        // Matches key="value" or key='value'
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

    /**
     * Convert markdown text to HTML cleanly without external dependencies.
     */
    public function convertMarkdownToHtml(string $markdown): string
    {
        // Normalize line endings
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);

        // Split by double newlines to process block-level elements
        $blocks = explode("\n\n", $markdown);
        $htmlBlocks = [];

        $inUnorderedList = false;
        $inOrderedList = false;
        $ulContent = [];
        $olContent = [];

        $closeActiveLists = function () use (&$inUnorderedList, &$inOrderedList, &$ulContent, &$olContent, &$htmlBlocks) {
            if ($inUnorderedList) {
                $htmlBlocks[] = "<ul>\n".implode("\n", $ulContent)."\n</ul>";
                $inUnorderedList = false;
                $ulContent = [];
            }
            if ($inOrderedList) {
                $htmlBlocks[] = "<ol>\n".implode("\n", $olContent)."\n</ol>";
                $inOrderedList = false;
                $olContent = [];
            }
        };

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            // 1. Check if it's a heading
            if (preg_match('/^(#{1,6})\s+(.+)$/m', $block, $matches)) {
                $closeActiveLists();
                $level = strlen($matches[1]);
                $content = $this->parseInlineMarkdown($matches[2]);
                $htmlBlocks[] = "<h{$level}>{$content}</h{$level}>";

                continue;
            }

            // 2. Check if it's a blockquote
            if (str_starts_with($block, '>')) {
                $closeActiveLists();
                $lines = explode("\n", $block);
                $quoteLines = [];
                foreach ($lines as $line) {
                    $quoteLines[] = ltrim(preg_replace('/^>\s?/', '', $line));
                }
                $quoteContent = implode("\n", $quoteLines);
                $quoteHtml = $this->convertMarkdownToHtml($quoteContent);
                $htmlBlocks[] = "<blockquote>\n{$quoteHtml}\n</blockquote>";

                continue;
            }

            // 3. Check if it's an unordered list block
            $lines = explode("\n", $block);
            $firstLine = trim($lines[0]);
            if (preg_match('/^[\*\-\+]\s+(.+)$/', $firstLine, $matches)) {
                if ($inOrderedList) {
                    $closeActiveLists();
                }
                $inUnorderedList = true;
                foreach ($lines as $line) {
                    $lineTrimmed = trim($line);
                    if (preg_match('/^[\*\-\+]\s+(.+)$/', $lineTrimmed, $liMatches)) {
                        $ulContent[] = '  <li>'.$this->parseInlineMarkdown($liMatches[1]).'</li>';
                    } else {
                        // Continuation of previous list item
                        if (! empty($ulContent)) {
                            $lastIdx = count($ulContent) - 1;
                            $ulContent[$lastIdx] = substr($ulContent[$lastIdx], 0, -5).' '.$this->parseInlineMarkdown($lineTrimmed).'</li>';
                        }
                    }
                }

                continue;
            }

            // 4. Check if it's an ordered list block
            if (preg_match('/^\d+\.\s+(.+)$/', $firstLine, $matches)) {
                if ($inUnorderedList) {
                    $closeActiveLists();
                }
                $inOrderedList = true;
                foreach ($lines as $line) {
                    $lineTrimmed = trim($line);
                    if (preg_match('/^\d+\.\s+(.+)$/', $lineTrimmed, $liMatches)) {
                        $olContent[] = '  <li>'.$this->parseInlineMarkdown($liMatches[1]).'</li>';
                    } else {
                        // Continuation of previous list item
                        if (! empty($olContent)) {
                            $lastIdx = count($olContent) - 1;
                            $olContent[$lastIdx] = substr($olContent[$lastIdx], 0, -5).' '.$this->parseInlineMarkdown($lineTrimmed).'</li>';
                        }
                    }
                }

                continue;
            }

            // Paragraph block
            $closeActiveLists();
            $content = $this->parseInlineMarkdown($block);
            $htmlBlocks[] = "<p>{$content}</p>";
        }

        // Close any remaining open lists
        $closeActiveLists();

        return implode("\n\n", $htmlBlocks);
    }

    /**
     * Parse inline markdown (bold, italic, links, inline code).
     */
    protected function parseInlineMarkdown(string $text): string
    {
        // Escape HTML tags for security
        $text = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');

        // Bold: **text** or __text__
        $text = preg_replace('/(\*\*|__)(.*?)\1/', '<strong>$2</strong>', $text);

        // Italics: *text* or _text_
        $text = preg_replace('/(\*|_)(.*?)\1/', '<em>$2</em>', $text);

        // Links: [anchor](url)
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($matches) {
            $anchor = $matches[1];
            $url = htmlspecialchars_decode($matches[2]);

            // Validate scheme to prevent malicious protocols (javascript:, data:)
            $scheme = parse_url($url, PHP_URL_SCHEME);
            if ($scheme !== null && ! in_array(strtolower($scheme), ['http', 'https'])) {
                return htmlspecialchars($anchor, ENT_QUOTES, 'UTF-8');
            }

            return '<a href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($anchor, ENT_QUOTES, 'UTF-8').'</a>';
        }, $text);

        // Inline code: `code`
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);

        return $text;
    }
}
