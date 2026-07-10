<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Services;

use App\Modules\AIProviderManager\Services\AIProviderService;
use App\Modules\ContentPipeline\Contracts\ContentGeneratorInterface;
use App\Modules\ContentPipeline\DTOs\PipelineContext;
use Illuminate\Support\Facades\Log;

class ContentGeneratorService implements ContentGeneratorInterface
{
    public function __construct(
        protected AIProviderService $providerService,
        protected PromptEngine $promptEngine
    ) {}

    /**
     * Process the content generation stage of the content pipeline.
     */
    public function handle(PipelineContext $context): PipelineContext
    {
        try {
            Log::info('ContentGeneratorService: Starting news content generation.');

            $pipeline       = $context->pipeline;
            // Use an override provider injected by the failover loop when present,
            // otherwise fall back to the pipeline's own configured provider.
            $provider       = $context->overrideProvider ?? $pipeline->provider;
            $promptTemplate = $pipeline->prompt;
            $site           = $pipeline->site;

            if (! $pipeline || ! $provider || ! $promptTemplate || ! $site) {
                throw new \RuntimeException('Incomplete pipeline dependencies in context.');
            }

            // Validate prompt template has content - the field is 'prompt' not 'template'
            $promptText = $promptTemplate->prompt ?? null;
            if (empty($promptText)) {
                Log::warning('ContentGeneratorService: Prompt template is empty, using default.', [
                    'prompt_id' => $promptTemplate->id,
                    'prompt_name' => $promptTemplate->name,
                ]);
                $promptText = 'Write a comprehensive news article about {{category}} covering the latest developments. Include relevant facts, quotes from sources, and analysis. Target audience: general readers interested in {{category}} news.';
            }

            // 1. Resolve variables from category context (no topic model needed)
            $category = $context->metadata['news_category']
                ?? strtolower($pipeline->news_category ?? 'global');

            $categoryLabel = $context->metadata['resolved_topic_category']
                ?? ucfirst($category);

            $language = $context->metadata['language'] ?? ($pipeline->language ?: 'en');
            $website  = $site->domain_url;

            // Dynamically resolve journalistic tone based on category
            $toneMap = [
                'global'        => 'Neutral and authoritative — facts-first, globally balanced reporting',
                'trending'      => 'Confident and timely — highlights why the story matters right now',
                'local'         => 'Conversational and community-focused — warm, approachable local voice',
                'technology'    => 'Precise and forward-looking — expert-level clarity on tech developments',
                'business'      => 'Analytical and measured — data-driven financial and market reporting',
                'politics'      => 'Balanced and impartial — objective multi-perspective political coverage',
                'sports'        => 'Energetic and direct — results-focused with competitive edge',
                'health'        => 'Reassuring and evidence-based — verified medical and wellness information',
                'science'       => 'Curious and methodical — research-backed scientific explanations',
                'entertainment' => 'Engaging and vivid — cultural and entertainment storytelling',
            ];
            $tone = $toneMap[$category] ?? 'Neutral and professional news reporting';

            // Resolve focus keywords from extracted facts
            $facts       = $context->metadata['extracted_facts'] ?? $context->researchData['extracted_facts'] ?? [];
            $keywordsList = $facts['keywords'] ?? [];
            if (empty($keywordsList)) {
                // Fall back to category-based keywords
                $keywordsList = [$categoryLabel, 'news', 'today', date('Y')];
            }
            $keywords = implode(', ', array_slice($keywordsList, 0, 5));

            $variables = [
                'category' => $categoryLabel,
                'language' => $language,
                'website'  => $website,
                'tone'     => $tone,
                'keywords' => $keywords,
                'Keywords' => $keywords,
                'date'     => now()->format('F j, Y'),
            ];

            // Newsroom workflow: anchor generation to the employee-selected
            // news candidate when present. Adds headline/summary/sources
            // variables for prompt templates; legacy runs are unaffected.
            $selectedNews = $context->metadata['selected_news'] ?? null;
            if (is_array($selectedNews) && ! empty($selectedNews['title'])) {
                $variables['headline'] = $selectedNews['title'];
                $variables['summary']  = (string) ($selectedNews['summary'] ?? '');
                $variables['sources']  = implode(', ', array_filter(
                    array_column((array) ($selectedNews['source_references'] ?? []), 'url')
                ));

                $candidateKeywords = array_filter(array_map('strval', (array) ($selectedNews['keywords'] ?? [])));
                if (! empty($candidateKeywords)) {
                    $variables['keywords'] = implode(', ', array_slice($candidateKeywords, 0, 5));
                    $variables['Keywords'] = $variables['keywords'];
                }
            } else {
                $variables['headline'] = $categoryLabel . " Updates";
                $variables['summary']  = "Latest current events, news developments, and analysis on " . $categoryLabel . " in " . ($pipeline->target_country ?: "Global");
                $variables['sources']  = $website;
            }

            // 2. Modular prompt compilation
            $compiledPrompt = $this->promptEngine->buildFullPrompt(
                $context,
                $promptText,
                $variables
            );

            Log::debug('ContentGeneratorService: Compiled prompt for generation.', [
                'prompt_id' => $promptTemplate->id,
                'prompt_name' => $promptTemplate->name,
                'prompt_length' => strlen($promptText),
            ]);

            // 3. Resolve driver and decrypt api key
            $client = $this->providerService->getDriver($provider->provider_key);
            $apiKey = $provider->api_key;
            if (empty($apiKey)) {
                throw new \RuntimeException("API key for provider '{$provider->name}' is missing.");
            }

            // 4. Measure execution time and call provider
            $startTime      = microtime(true);
            $result         = $client->generate($apiKey, $compiledPrompt, $provider->default_model);
            $executionTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            // 5. Structure results into context
            $context->generatedContent = $result['text'];

            // Title: Category News — Date  (news-appropriate format)
            $title          = "{$categoryLabel} News: " . now()->format('F j, Y');
            $context->title = $title;

            // Merge token/cost metadata
            $context->metadata['prompt_tokens']      = $result['prompt_tokens'] ?? 0;
            $context->metadata['completion_tokens']  = $result['completion_tokens'] ?? 0;
            $context->metadata['total_tokens']       = $result['total_tokens'] ?? 0;
            $context->metadata['estimated_cost']     = $result['estimated_cost'] ?? 0.0;
            $context->metadata['raw_response']       = $result['raw_response'] ?? [];
            $context->metadata['execution_time_ms']  = $executionTimeMs;

            // Update rate limits in database
            if ($provider instanceof AIProvider && !empty($result['rate_limits'])) {
                $limits = $result['rate_limits'];
                $provider->updateRateLimits(
                    isset($limits['limit']) ? intval($limits['limit']) : null,
                    isset($limits['remaining']) ? intval($limits['remaining']) : null,
                    $limits['reset'] ?? null
                );
            }

            Log::info('ContentGeneratorService: News content generated successfully.', [
                'title'            => $title,
                'category'         => $category,
                'execution_time_ms' => $executionTimeMs,
                'prompt_tokens'    => $result['prompt_tokens'] ?? 0,
                'completion_tokens' => $result['completion_tokens'] ?? 0,
            ]);

        } catch (\Exception $e) {
            if (isset($provider) && $provider instanceof AIProvider) {
                $provider->handleFailure($e);
            }
            Log::error('ContentGeneratorService failed: '.$e->getMessage(), [
                'exception' => $e,
            ]);
            $context->addError('content_generator', $e->getMessage());
            throw $e;
        }

        return $context;
    }

    /**
     * Parse variables in prompt templates.
     */
    protected function compilePrompt(string $template, array $variables): string
    {
        return $this->promptEngine->compileUserPrompt($template, $variables);
    }
}
