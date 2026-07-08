<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Services;

use App\Modules\AIProviderManager\Services\AIProviderService;
use App\Modules\ContentPipeline\Contracts\ContentGeneratorInterface;
use App\Modules\ContentPipeline\DTOs\PipelineContext;
use App\Modules\ContentPipeline\Services\PromptEngine;
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
            Log::info('ContentGeneratorService: Starting content generation.');

            $pipeline = $context->pipeline;
            $provider = $pipeline->provider;
            $promptTemplate = $pipeline->prompt;
            $site = $pipeline->site;

            if (!$pipeline || !$provider || !$promptTemplate || !$site) {
                throw new \RuntimeException('Incomplete pipeline dependencies in context.');
            }

            // 1. Compile variables
            $topic = $pipeline->topic;
            $topicName = $context->resolvedTopic ?? ($topic ? $topic->name : '');
            $category = $context->metadata['resolved_topic_category'] ?? ($topic ? $topic->category : 'General');
            $language = $context->metadata['language'] ?? ($pipeline->language ?: 'en');
            $website = $site->domain_url;

            $variables = [
                'topic' => $topicName,
                'category' => $category,
                'language' => $language,
                'website' => $website,
            ];

            // 2. Perform modular prompt compilation
            $compiledPrompt = $this->promptEngine->buildFullPrompt($context, $promptTemplate->prompt, $variables);

            // 3. Resolve driver and decrypt api key
            $client = $this->providerService->getDriver($provider->provider_key);
            $apiKey = $provider->api_key;
            if (empty($apiKey)) {
                throw new \RuntimeException("API key for provider '{$provider->name}' is missing.");
            }

            // 4. Measure execution time and call provider
            $startTime = microtime(true);
            $result = $client->generate($apiKey, $compiledPrompt, $provider->default_model);
            $executionTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            // 5. Structure results into context
            $context->generatedContent = $result['text'];
            
            // Determine title (Topic + Date, matching test expectations)
            $title = "Article: {$topicName} - " . now()->format('Y-m-d');
            $context->title = $title;

            // Merge details into context metadata
            $context->metadata['prompt_tokens'] = $result['prompt_tokens'] ?? 0;
            $context->metadata['completion_tokens'] = $result['completion_tokens'] ?? 0;
            $context->metadata['total_tokens'] = $result['total_tokens'] ?? 0;
            $context->metadata['estimated_cost'] = $result['estimated_cost'] ?? 0.0;
            $context->metadata['raw_response'] = $result['raw_response'] ?? [];
            $context->metadata['execution_time_ms'] = $executionTimeMs;

            Log::info('ContentGeneratorService: Content generated successfully.', [
                'title' => $title,
                'execution_time_ms' => $executionTimeMs,
                'prompt_tokens' => $result['prompt_tokens'] ?? 0,
                'completion_tokens' => $result['completion_tokens'] ?? 0,
            ]);

        } catch (\Exception $e) {
            Log::error('ContentGeneratorService failed: ' . $e->getMessage(), [
                'exception' => $e
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
