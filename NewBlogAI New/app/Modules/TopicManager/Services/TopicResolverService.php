<?php

declare(strict_types=1);

namespace App\Modules\TopicManager\Services;

use App\Modules\ContentPipeline\Contracts\TopicResolverInterface;
use App\Modules\ContentPipeline\DTOs\PipelineContext;
use Illuminate\Support\Facades\Log;

class TopicResolverService implements TopicResolverInterface
{
    /**
     * Process the current stage of the content pipeline.
     * Resolves the topic's category, name, language, and determines the region/locale.
     */
    public function handle(PipelineContext $context): PipelineContext
    {
        try {
            Log::info('TopicResolverService: Resolving topic details.');

            $pipeline = $context->pipeline;
            $topic = $pipeline->topic ?? null;

            if (!$topic) {
                // Attempt to load topic if not present in the relation
                $pipeline->loadMissing('topic');
                $topic = $pipeline->topic;
            }

            if (!$topic) {
                throw new \RuntimeException('No topic associated with the pipeline context.');
            }

            // Resolve category and name
            $name = $topic->name;
            $category = $topic->category ?? 'General';

            // Resolve language: check pipeline language, fall back to topic language, default to 'en'
            $language = $pipeline->language ?: ($topic->language ?: 'en');

            // Determine region/locale based on language
            $locale = $this->determineLocale($language);
            $region = $this->determineRegion($locale);

            // Set context properties
            $context->resolvedTopic = $name;
            
            // Store resolved details in metadata
            $context->metadata['resolved_topic_name'] = $name;
            $context->metadata['resolved_topic_category'] = $category;
            $context->metadata['language'] = $language;
            $context->metadata['locale'] = $locale;
            $context->metadata['region'] = $region;

            Log::info('TopicResolverService: Topic resolved successfully.', [
                'name' => $name,
                'category' => $category,
                'language' => $language,
                'locale' => $locale,
                'region' => $region
            ]);
        } catch (\Exception $e) {
            Log::error('TopicResolverService failed: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            $context->addError('topic_resolver', $e->getMessage());
        }

        return $context;
    }

    /**
     * Normalize the language code into a valid locale.
     */
    protected function determineLocale(string $language): string
    {
        // Normalise language/locale format
        $lang = strtolower(str_replace('_', '-', $language));

        // If already has country suffix (e.g. en-us), return normalized
        if (str_contains($lang, '-')) {
            return $lang;
        }

        // Map simple language codes to default locales
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
        ];

        return $map[$lang] ?? $lang;
    }

    /**
     * Determine region code from locale.
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
