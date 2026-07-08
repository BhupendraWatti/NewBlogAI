<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\ContentPipeline\Contracts\FactExtractorInterface;
use App\Modules\ContentPipeline\Contracts\ResearchServiceInterface;
use App\Modules\ContentPipeline\Contracts\SourceCollectorInterface;
use App\Modules\ContentPipeline\Contracts\TopicResolverInterface;
use App\Modules\ContentPipeline\DTOs\PipelineContext;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\SiteManager\Models\Site;
use App\Modules\TopicManager\Models\Topic;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\AIProviderManager\Models\AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineServicesImplementationTest extends TestCase
{
    use RefreshDatabase;

    protected Site $site;
    protected Topic $topic;
    protected Prompt $prompt;
    protected AIProvider $provider;
    protected ContentPipeline $pipeline;
    protected PipelineRun $run;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::create([
            'domain_url' => 'https://example-test.com',
            'api_key' => 'test-token',
            'is_active' => true,
        ]);

        $this->topic = Topic::create([
            'name' => 'Laravel 12 Features',
            'category' => 'Tech',
            'language' => 'en',
            'status' => 'active',
            'generation_frequency' => 'daily',
        ]);

        $this->prompt = Prompt::create([
            'name' => 'Test Prompt',
            'prompt' => 'Write about {{topic}}',
            'category' => 'Tech',
            'status' => 'active',
        ]);

        $this->provider = AIProvider::create([
            'provider_key' => 'gemini',
            'name' => 'Google Gemini',
            'api_key' => 'some-api-key',
            'default_model' => 'gemini-2.5-flash',
            'is_enabled' => true,
        ]);

        $this->pipeline = ContentPipeline::create([
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'prompt_id' => $this->prompt->id,
            'ai_provider_id' => $this->provider->id,
            'language' => 'en',
            'news_category' => 'technology',
            'generation_type' => 'article',
            'is_active' => true,
        ]);

        $this->run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'processing',
        ]);
    }

    public function test_topic_resolver_service(): void
    {
        $resolver = app(TopicResolverInterface::class);
        $context = new PipelineContext($this->run, $this->pipeline);

        $context = $resolver->handle($context);

        $this->assertFalse($context->hasErrors());
        $this->assertEquals('latest technology news today', $context->resolvedTopic);
        $this->assertEquals('Technology', $context->metadata['resolved_topic_category']);
        $this->assertEquals('en', $context->metadata['language']);
        $this->assertEquals('en-US', $context->metadata['locale']);
        $this->assertEquals('US', $context->metadata['region']);
    }

    /**
     * Test the ResearchService generates search queries based on the topic category.
     */
    public function test_research_service(): void
    {
        $resolver = app(TopicResolverInterface::class);
        $research = app(ResearchServiceInterface::class);

        $context = new PipelineContext($this->run, $this->pipeline);
        $context = $resolver->handle($context);
        $context = $research->handle($context);

        $this->assertFalse($context->hasErrors());
        $this->assertNotEmpty($context->researchData['queries']);
        $this->assertStringContainsString('latest technology news today', $context->researchData['queries'][0]);
        $this->assertNotNull($context->researchData['researched_at']);
    }

    /**
     * Test the SourceCollectionService collects normalized sources and removes duplicates by URL.
     */
    public function test_source_collection_service(): void
    {
        $resolver = app(TopicResolverInterface::class);
        $research = app(ResearchServiceInterface::class);
        $collector = app(SourceCollectorInterface::class);

        $context = new PipelineContext($this->run, $this->pipeline);
        $context = $resolver->handle($context);
        
        // Inject a duplicate query to ensure the collector filters out duplicate URLs
        $context->addResearchData('queries', [
            'Laravel 12 Features latest updates',
            'Laravel 12 Features latest updates', // exact duplicate query
        ]);

        $context = $collector->handle($context);

        $this->assertFalse($context->hasErrors());
        $this->assertNotEmpty($context->sources);

        $urls = array_map(fn($source) => $source['url'], $context->sources);
        $this->assertEquals(count($urls), count(array_unique($urls)), 'Duplicate URLs should be removed');

        foreach ($context->sources as $source) {
            $this->assertArrayHasKey('url', $source);
            $this->assertArrayHasKey('title', $source);
            $this->assertArrayHasKey('snippet', $source);
            $this->assertArrayHasKey('metadata', $source);
        }
    }

    /**
     * Test the FactExtractionService extracts people, organizations, locations, dates, events, and keywords.
     */
    public function test_fact_extraction_service(): void
    {
        $resolver = app(TopicResolverInterface::class);
        $research = app(ResearchServiceInterface::class);
        $collector = app(SourceCollectorInterface::class);
        $extractor = app(FactExtractorInterface::class);

        $context = new PipelineContext($this->run, $this->pipeline);
        $context = $resolver->handle($context);
        $context = $research->handle($context);
        $context = $collector->handle($context);
        $context = $extractor->handle($context);

        $this->assertFalse($context->hasErrors());
        $this->assertArrayHasKey('extracted_facts', $context->metadata);
        
        $facts = $context->metadata['extracted_facts'];
        $this->assertIsArray($facts['people']);
        $this->assertIsArray($facts['organizations']);
        $this->assertIsArray($facts['locations']);
        $this->assertIsArray($facts['dates']);
        $this->assertIsArray($facts['events']);
        $this->assertIsArray($facts['keywords']);

        // Since it is 'latest technology news today', let's check standard output contains some keywords
        $this->assertContains('technology', $facts['keywords']);
    }

    /**
     * Test the MediaPreparationService converts markdown to HTML, replaces placeholders, and generates featured images.
     */
    public function test_media_preparation_service(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $dummyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        \Illuminate\Support\Facades\Http::fake([
            'https://image.pollinations.ai/*' => \Illuminate\Support\Facades\Http::response($dummyPng, 200, [
                'Content-Type' => 'image/png',
                'Content-Length' => (string) strlen($dummyPng),
            ]),
        ]);

        $preparator = app(\App\Modules\ContentPipeline\Contracts\MediaPreparatorInterface::class);
        $context = new PipelineContext($this->run, $this->pipeline);
        $context->resolvedTopic = 'Laravel 12 Features';
        $context->title = 'Article: Laravel 12 Features - ' . now()->format('Y-m-d');
        $context->generatedContent = "# Laravel 12\n\nThis is a paragraph with an inline image: <!-- image-placeholder: prompt=\"beautiful laravel code\" alt=\"Laravel Code\" caption=\"Clean Laravel 12\" -->\n\nEnd.";

        $context = $preparator->handle($context);

        $this->assertFalse($context->hasErrors());
        $this->assertNotNull($context->metadata['featured_image_id']);
        $this->assertNotNull($context->metadata['featured_image_url']);
        $this->assertNotNull($context->metadata['processed_at']);

        $html = $context->generatedContent;
        $this->assertStringStartsWith('<div class="post-featured-image"', $html);
        $this->assertStringContainsString('<h1>Laravel 12</h1>', $html);
        $this->assertStringContainsString('<figure class="wp-block-image size-large"', $html);
        $this->assertStringContainsString('alt="Laravel Code"', $html);
    }

    /**
     * Test the PublishingQueueService stores GeneratedContent, ContentRevision, updates statuses, and resolves reservations.
     */
    public function test_publishing_queue_service(): void
    {
        $queue = app(\App\Modules\ContentPipeline\Contracts\PublishingQueueInterface::class);
        $context = new PipelineContext($this->run, $this->pipeline);
        $context->resolvedTopic = 'Laravel 12 Features';
        $context->title = 'Article: Laravel 12 Features - ' . now()->format('Y-m-d');
        $context->generatedContent = '<div class="post-featured-image">...</div><h1>Laravel 12</h1><p>Content.</p>';
        $context->metadata['prompt_tokens'] = 150;
        $context->metadata['completion_tokens'] = 200;
        $context->metadata['total_tokens'] = 350;
        $context->metadata['estimated_cost'] = 0.05;

        $context = $queue->handle($context);

        $this->assertFalse($context->hasErrors());
        $generatedContent = $context->metadata['generated_content_model'] ?? null;
        $this->assertInstanceOf(\App\Modules\ContentGeneration\Models\GeneratedContent::class, $generatedContent);

        // Verify status is generated
        $this->assertEquals('generated', $generatedContent->status);

        // Verify database records
        $this->assertDatabaseHas('generated_contents', [
            'id' => $generatedContent->id,
            'title' => $context->title,
            'status' => 'generated',
        ]);

        $this->assertDatabaseHas('content_revisions', [
            'generated_content_id' => $generatedContent->id,
            'title' => $context->title,
        ]);

        $this->assertDatabaseHas('ai_request_logs', [
            'site_id' => $this->site->id,
            'status' => 'success',
            'prompt_tokens' => 150,
            'completion_tokens' => 200,
            'total_tokens' => 350,
        ]);

        // Verify run and pipeline status transitions
        $this->assertEquals('completed', $this->run->fresh()->status);
        $this->assertEquals('completed', $this->pipeline->fresh()->status);
    }
}
