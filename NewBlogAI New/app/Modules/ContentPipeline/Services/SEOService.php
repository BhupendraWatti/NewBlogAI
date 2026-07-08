<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Services;

use App\Modules\ContentPipeline\Contracts\SEOServiceInterface;
use App\Modules\ContentPipeline\DTOs\PipelineContext;
use Illuminate\Support\Facades\Log;

class SEOService implements SEOServiceInterface
{
    /**
     * Process the SEO optimization stage of the content pipeline.
     */
    public function handle(PipelineContext $context): PipelineContext
    {
        try {
            Log::info('SEOService: Starting SEO metadata generation.');

            $title = $context->title ?? $context->resolvedTopic ?? 'Untitled Article';
            $content = $context->generatedContent ?? '';

            // 1. SEO Title
            $seoTitle = $title;

            // 2. Meta Description (First 155-160 characters from generatedContent stripped of markdown and HTML)
            $cleanContent = strip_tags($content);
            $cleanContent = preg_replace('/[#*_`~\[\]\(\)]/', '', $cleanContent);
            $cleanContent = preg_replace('/\s+/', ' ', $cleanContent);
            $cleanContent = trim($cleanContent);
            
            $metaDescription = mb_substr($cleanContent, 0, 155);
            if (mb_strlen($cleanContent) > 155) {
                $metaDescription .= '...';
            }

            // 3. URL slug (kebab-case, based on title/topic)
            $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-'));

            // 4. Focus Keywords
            $focusKeywords = [];
            if (!empty($context->resolvedTopic)) {
                $focusKeywords[] = strtolower($context->resolvedTopic);
                $words = explode(' ', strtolower(preg_replace('/[^a-zA-Z0-9 ]/', '', $context->resolvedTopic)));
                foreach ($words as $word) {
                    if (strlen($word) > 3 && !in_array($word, $focusKeywords, true)) {
                        $focusKeywords[] = $word;
                    }
                }
            }
            if (empty($focusKeywords)) {
                $focusKeywords = ['article', 'content'];
            }

            // 5. Internal keyword suggestions (suggesting links based on topic keywords)
            $internalSuggestions = [];
            foreach ($focusKeywords as $keyword) {
                if (strlen($keyword) > 3) {
                    $internalSuggestions[] = [
                        'keyword' => $keyword,
                        'suggested_link' => '/' . str_replace(' ', '-', $keyword),
                    ];
                }
            }

            // 6. Open Graph metadata
            $ogTitle = $seoTitle;
            $ogDescription = $metaDescription;
            $ogType = 'article';
            $siteUrl = $context->pipeline->site->domain_url ?? '';
            $ogUrl = $siteUrl ? rtrim($siteUrl, '/') . '/' . $slug : '/' . $slug;

            // 7. Twitter Card metadata
            $twitterCard = 'summary_large_image';
            $twitterTitle = $seoTitle;
            $twitterDescription = $metaDescription;

            // 8. Schema-ready structured data (JSON-LD format for article schema)
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $seoTitle,
                'description' => $metaDescription,
                'datePublished' => now()->toIso8601String(),
                'author' => [
                    '@type' => 'Organization',
                    'name' => $context->pipeline->site->name ?? 'AI Publisher',
                ],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => $context->pipeline->site->name ?? 'AI Publisher',
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => $context->metadata['featured_image_url'] ?? '',
                    ],
                ],
                'image' => $context->metadata['featured_image_url'] ?? '',
            ];

            // Structure cleanly inside $context->metadata['seo']
            $context->metadata['seo'] = [
                'title' => $seoTitle,
                'meta_description' => $metaDescription,
                'slug' => $slug,
                'focus_keywords' => $focusKeywords,
                'internal_keyword_suggestions' => $internalSuggestions,
                'og_metadata' => [
                    'og:title' => $ogTitle,
                    'og:description' => $ogDescription,
                    'og:type' => $ogType,
                    'og:url' => $ogUrl,
                ],
                'twitter_metadata' => [
                    'twitter:card' => $twitterCard,
                    'twitter:title' => $twitterTitle,
                    'twitter:description' => $twitterDescription,
                ],
                'schema' => $schema,
            ];

            Log::info('SEOService: SEO metadata generated successfully.', [
                'slug' => $slug,
                'focus_keywords' => $focusKeywords,
            ]);

        } catch (\Exception $e) {
            Log::error('SEOService failed: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            $context->addError('seo_service', $e->getMessage());
            throw $e;
        }

        return $context;
    }
}
