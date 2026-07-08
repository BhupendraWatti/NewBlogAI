<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\ContentPipeline\Contracts\SourceCollectorInterface;
use App\Modules\ContentPipeline\DTOs\PipelineContext;
use App\Modules\ContentPipeline\DTOs\SourceDTO;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\SiteManager\Models\Site;
use App\Modules\TopicManager\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SourceIntelligenceTest extends TestCase
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
            'name' => 'Laravel 12 Artificial Intelligence Integration',
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
            'generation_type' => 'article',
            'is_active' => true,
        ]);

        $this->run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'processing',
        ]);
    }

    /**
     * Verify that duplicate sources are strictly filtered based on normalized URLs.
     */
    public function test_duplicate_sources_are_filtered(): void
    {
        $collector = app(SourceCollectorInterface::class);
        $context = new PipelineContext($this->run, $this->pipeline);

        // Inject sources that are duplicates under normalization
        $context->addSource([
            'url' => 'https://example.com/path/', // trailing slash, lowercase
            'title' => 'First Source',
            'snippet' => 'Information about Laravel AI features.',
            'metadata' => [
                'author' => 'Author A',
                'publisher' => 'Publisher A',
                'published_date' => '2026-07-01'
            ]
        ]);

        $context->addSource([
            'url' => 'HTTPS://EXAMPLE.COM/path', // uppercase scheme/host, no trailing slash
            'title' => 'Second Source (Duplicate)',
            'snippet' => 'Information about Laravel AI features.',
            'metadata' => [
                'author' => 'Author B',
                'publisher' => 'Publisher B',
                'published_date' => '2026-07-02'
            ]
        ]);

        // Run collector
        $context = $collector->handle($context);

        $this->assertFalse($context->hasErrors());
        
        $urls = array_map(fn($source) => $source['url'], $context->sources);
        $this->assertCount(1, $urls);
        $this->assertEquals('https://example.com/path', $urls[0]);
    }

    /**
     * Verify that sources are ranked based on calculated relevance and metadata completeness.
     */
    public function test_sources_are_ranked_by_calculated_relevance(): void
    {
        $collector = app(SourceCollectorInterface::class);
        $context = new PipelineContext($this->run, $this->pipeline);

        $context->resolvedTopic = 'Laravel 12 Artificial Intelligence Integration';
        $context->addResearchData('queries', ['Laravel 12 AI']);

        // Source A: Low relevance (no matching terms, incomplete metadata)
        $context->addSource([
            'url' => 'https://low-relevance.com/page',
            'title' => 'Unrelated Topic Article',
            'snippet' => 'This page discusses cooking and baking recipes.',
            'metadata' => []
        ]);

        // Source B: Medium relevance (some matches with "Laravel", moderate metadata)
        $context->addSource([
            'url' => 'https://medium-relevance.com/page',
            'title' => 'Introduction to Laravel Web Development',
            'snippet' => 'Exploring the PHP framework Laravel, which can be useful for web apps.',
            'metadata' => [
                'author' => 'John Smith',
                'publisher' => 'Laravel Devs',
                'published_date' => '2026-05-15'
            ]
        ]);

        // Source C: High relevance (many matching keywords, complete metadata)
        $context->addSource([
            'url' => 'https://high-relevance.com/page',
            'title' => 'Laravel 12 and Artificial Intelligence Integration Guide',
            'snippet' => 'This guide covers the integration of Laravel 12 with modern Artificial Intelligence tools.',
            'metadata' => [
                'author' => 'Jane Doe',
                'publisher' => 'AI Tech Journal',
                'published_date' => '2026-07-06',
                'keywords' => ['laravel', 'artificial', 'intelligence', 'integration']
            ]
        ]);

        $context = $collector->handle($context);

        $this->assertFalse($context->hasErrors());

        $sources = $context->sources;
        
        $highRelevanceIndex = -1;
        $mediumRelevanceIndex = -1;
        $lowRelevanceIndex = -1;

        foreach ($sources as $index => $source) {
            if ($source['url'] === 'https://high-relevance.com/page') {
                $highRelevanceIndex = $index;
            } elseif ($source['url'] === 'https://medium-relevance.com/page') {
                $mediumRelevanceIndex = $index;
            } elseif ($source['url'] === 'https://low-relevance.com/page') {
                $lowRelevanceIndex = $index;
            }
        }

        $this->assertNotEquals(-1, $highRelevanceIndex);
        $this->assertNotEquals(-1, $mediumRelevanceIndex);
        $this->assertNotEquals(-1, $lowRelevanceIndex);

        // Higher relevance should have smaller index (ranked higher)
        $this->assertLessThan($mediumRelevanceIndex, $highRelevanceIndex, 'High relevance should rank above medium relevance');
        $this->assertLessThan($lowRelevanceIndex, $mediumRelevanceIndex, 'Medium relevance should rank above low relevance');

        // Verify that relevance score is a dynamic float value
        foreach ($sources as $source) {
            $this->assertIsFloat($source['relevance_score']);
            $this->assertGreaterThan(0.0, $source['relevance_score']);
        }
    }

    /**
     * Verify that topic clustering correctly groups related sources.
     */
    public function test_topic_clustering_correctly_groups_related_sources(): void
    {
        $collector = app(SourceCollectorInterface::class);
        $context = new PipelineContext($this->run, $this->pipeline);

        // Source 1 and Source 2 share the keyword "docker"
        $context->addSource([
            'url' => 'https://docker1.com',
            'title' => 'Docker for Laravel developers',
            'snippet' => 'Setting up containers for local development.',
            'metadata' => [
                'keywords' => ['docker', 'laravel', 'containers']
            ]
        ]);

        $context->addSource([
            'url' => 'https://docker2.com',
            'title' => 'Advanced Docker deployments',
            'snippet' => 'Deploying production docker images for web apps.',
            'metadata' => [
                'keywords' => ['docker', 'deployment', 'production']
            ]
        ]);

        // Source 3 and Source 4 share the keyword "aws"
        $context->addSource([
            'url' => 'https://aws1.com',
            'title' => 'AWS ECS Deployment Guide',
            'snippet' => 'Setting up AWS ECS containers for hosting apps.',
            'metadata' => [
                'keywords' => ['aws', 'ecs', 'cloud']
            ]
        ]);

        $context->addSource([
            'url' => 'https://aws2.com',
            'title' => 'AWS Cloud Services',
            'snippet' => 'A guide to cloud storage and database options on AWS.',
            'metadata' => [
                'keywords' => ['aws', 'cloud', 'database']
            ]
        ]);

        $context = $collector->handle($context);

        $this->assertFalse($context->hasErrors());

        $this->assertArrayHasKey('clustered_topics', $context->metadata);
        $this->assertArrayHasKey('topic_clusters', $context->metadata);

        $clusteredTopics = $context->metadata['clustered_topics'];
        $topicClusters = $context->metadata['topic_clusters'];

        $this->assertContains('docker', $clusteredTopics);
        $this->assertContains('aws', $clusteredTopics);

        $this->assertContains('https://docker1.com', $topicClusters['docker']);
        $this->assertContains('https://docker2.com', $topicClusters['docker']);

        $this->assertContains('https://aws1.com', $topicClusters['aws']);
        $this->assertContains('https://aws2.com', $topicClusters['aws']);
    }

    /**
     * Verify that region/locale detection correctly infers location from TLD or metadata clues.
     */
    public function test_region_locale_detection_infers_correctly(): void
    {
        $collector = app(SourceCollectorInterface::class);
        $context = new PipelineContext($this->run, $this->pipeline);

        // UK TLD
        $context->addSource([
            'url' => 'https://techblog.co.uk/news',
            'title' => 'UK Tech updates',
            'snippet' => 'Technology updates in Great Britain.',
            'metadata' => []
        ]);

        // German TLD
        $context->addSource([
            'url' => 'https://science.de/artikel',
            'title' => 'Science updates',
            'snippet' => 'German scientific discoveries.',
            'metadata' => []
        ]);

        // Indian text clues
        $context->addSource([
            'url' => 'https://someblog.com/post',
            'title' => 'Tech Scene in India',
            'snippet' => 'Discussing developments in Delhi and Mumbai.',
            'metadata' => [
                'publisher' => 'Indian Tech Blog'
            ]
        ]);

        $context = $collector->handle($context);

        $this->assertFalse($context->hasErrors());

        $ukSource = null;
        $deSource = null;
        $inSource = null;

        foreach ($context->sources as $src) {
            if (str_contains($src['url'], 'techblog.co.uk')) {
                $ukSource = $src;
            } elseif (str_contains($src['url'], 'science.de')) {
                $deSource = $src;
            } elseif (str_contains($src['url'], 'someblog.com')) {
                $inSource = $src;
            }
        }

        $this->assertNotNull($ukSource);
        $this->assertNotNull($deSource);
        $this->assertNotNull($inSource);

        $this->assertEquals('GB', $ukSource['metadata']['region']);
        $this->assertEquals('en-GB', $ukSource['metadata']['locale']);

        $this->assertEquals('DE', $deSource['metadata']['region']);
        $this->assertEquals('de-DE', $deSource['metadata']['locale']);

        $this->assertEquals('IN', $inSource['metadata']['region']);
        $this->assertEquals('en-IN', $inSource['metadata']['locale']);
    }
}
