<?php

declare(strict_types=1);

namespace App\Modules\TopicManager\Services;

use App\Modules\ContentPipeline\Contracts\TopicResolverInterface;
use App\Modules\ContentPipeline\DTOs\PipelineContext;
use Illuminate\Support\Facades\Log;

/**
 * CategoryResolverService (implements TopicResolverInterface for pipeline compatibility).
 *
 * Replaces the old topic-driven resolver. Instead of looking up a Topic model,
 * it reads the pipeline's `news_category` field and derives language/locale/region
 * context from the pipeline's own `language` setting.
 *
 * The resolved category is placed in `$context->resolvedTopic` (kept for downstream
 * services such as ResearchService and ContentGeneratorService) and in metadata
 * under `resolved_topic_category` and `resolved_topic_name`.
 */
class TopicResolverService implements TopicResolverInterface
{
    /**
     * Process the category resolution stage of the content pipeline.
     */
    public function handle(PipelineContext $context): PipelineContext
    {
        try {
            Log::info('CategoryResolverService: Resolving news category details.');

            $pipeline = $context->pipeline;
            $category = strtolower(trim($pipeline->news_category ?? 'global'));

            // Resolve language: use pipeline language field, default to 'en'
            $language = $pipeline->language ?: 'en';

            // Determine locale and region from language code
            $locale = $this->determineLocale($language);
            $region = $this->determineRegion($locale);

            // Derive a human-readable search subject from the category
            // (used as the "topic" keyword in research queries and prompts)
            $resolvedSubject = $this->categoryToSearchSubject($category);

            // Populate context — resolvedTopic carries the category subject for
            // downstream ResearchService and ContentGeneratorService compatibility
            $context->resolvedTopic = $resolvedSubject;

            $context->metadata['resolved_topic_name']     = $resolvedSubject;
            $context->metadata['resolved_topic_category'] = ucfirst($category);
            $context->metadata['news_category']           = $category;
            $context->metadata['language']                = $language;
            $context->metadata['locale']                  = $locale;
            $context->metadata['region']                  = $region;

            Log::info('CategoryResolverService: Category resolved successfully.', [
                'news_category'    => $category,
                'resolved_subject' => $resolvedSubject,
                'language'         => $language,
                'locale'           => $locale,
                'region'           => $region,
            ]);
        } catch (\Exception $e) {
            Log::error('CategoryResolverService failed: '.$e->getMessage(), [
                'exception' => $e,
            ]);
            $context->addError('category_resolver', $e->getMessage());
        }

        return $context;
    }

    /**
     * Map a news category slug to a meaningful search subject phrase used
     * by the research and content generation stages.
     */
    protected function categoryToSearchSubject(string $category): string
    {
        return match ($category) {
            'global'        => 'global news headlines today',
            'trending'      => 'trending news stories today',
            'local'         => 'local community news today',
            'technology'    => 'latest technology news today',
            'business'      => 'top business and finance news today',
            'politics'      => 'breaking political news today',
            'sports'        => 'top sports news and results today',
            'health'        => 'latest health and medical news today',
            'science'       => 'latest science and research news today',
            'entertainment' => 'top entertainment and culture news today',
            default         => 'latest news headlines today',
        };
    }

    /**
     * Normalize a language code into a valid BCP-47 locale string.
     */
    protected function determineLocale(string $language): string
    {
        $lang = strtolower(str_replace('_', '-', $language));

        if (str_contains($lang, '-')) {
            return $lang;
        }

        $map = [
            'en' => 'en-US',
            'es' => 'es-ES',
            'fr' => 'fr-FR',
            'de' => 'de-DE',
            'it' => 'it-IT',
            'pt' => 'pt-PT',
            'ja' => 'ja-JP',
            'zh' => 'zh-CN',
            'hi' => 'hi-IN',
            'ar' => 'ar-SA',
        ];

        return $map[$lang] ?? $lang;
    }

    /**
     * Determine region code from a locale string.
     */
    protected function determineRegion(string $locale): string
    {
        if (str_contains($locale, '-')) {
            [, $region] = explode('-', $locale, 2);

            return strtoupper($region);
        }

        return strtoupper($locale);
    }
}
