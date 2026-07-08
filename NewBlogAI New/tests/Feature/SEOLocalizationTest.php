<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\ContentPipeline\Contracts\SEOServiceInterface;
use App\Modules\ContentPipeline\Contracts\TranslationInterface;
use App\Modules\ContentPipeline\DTOs\PipelineContext;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\SiteManager\Models\Site;
use App\Modules\TopicManager\Models\Topic;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\AIProviderManager\Models\AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SEOLocalizationTest extends TestCase
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
            'name' => 'Laravel 12 Performance Tips',
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
     * Test the SEOService generates expected metadata.
     */
    public function test_seo_service_generates_metadata_correctly(): void
    {
        $seoService = app(SEOServiceInterface::class);

        $context = new PipelineContext($this->run, $this->pipeline);
        $context->resolvedTopic = 'Laravel 12 Performance Tips';
        $context->title = 'Article: Laravel 12 Performance Tips - 2026-07-07';
        $context->generatedContent = "# Laravel 12 Performance\n\nDiscover the best performance optimization tips for your next Laravel 12 project. We discuss database indexing, eager loading, and octane.\n\nMake sure to cache configuration files and use octane for maximum speed.";

        $context = $seoService->handle($context);

        $this->assertFalse($context->hasErrors());
        $this->assertArrayHasKey('seo', $context->metadata);

        $seo = $context->metadata['seo'];

        // Verify SEO title, meta description, and slug
        $this->assertEquals('Article: Laravel 12 Performance Tips - 2026-07-07', $seo['title']);
        $this->assertStringContainsString('Discover the best performance optimization tips', $seo['meta_description']);
        $this->assertLessThanOrEqual(158, strlen($seo['meta_description'])); // 155 + 3 chars ellipsis
        $this->assertEquals('article-laravel-12-performance-tips-2026-07-07', $seo['slug']);

        // Verify focus keywords and suggestions
        $this->assertNotEmpty($seo['focus_keywords']);
        $this->assertContains('laravel 12 performance tips', $seo['focus_keywords']);
        $this->assertContains('laravel', $seo['focus_keywords']);
        $this->assertContains('performance', $seo['focus_keywords']);
        $this->assertNotEmpty($seo['internal_keyword_suggestions']);
        $this->assertEquals('/laravel-12-performance-tips', $seo['internal_keyword_suggestions'][0]['suggested_link']);
        $this->assertEquals('/laravel', $seo['internal_keyword_suggestions'][1]['suggested_link']);

        // Verify OG tags
        $this->assertEquals($seo['title'], $seo['og_metadata']['og:title']);
        $this->assertEquals($seo['meta_description'], $seo['og_metadata']['og:description']);
        $this->assertEquals('article', $seo['og_metadata']['og:type']);
        $this->assertStringContainsString('/article-laravel-12-performance-tips-2026-07-07', $seo['og_metadata']['og:url']);

        // Verify Twitter tags
        $this->assertEquals('summary_large_image', $seo['twitter_metadata']['twitter:card']);
        $this->assertEquals($seo['title'], $seo['twitter_metadata']['twitter:title']);
        $this->assertEquals($seo['meta_description'], $seo['twitter_metadata']['twitter:description']);

        // Verify Schema-ready structured data
        $this->assertEquals('https://schema.org', $seo['schema']['@context']);
        $this->assertEquals('Article', $seo['schema']['@type']);
        $this->assertEquals($seo['title'], $seo['schema']['headline']);
        $this->assertEquals($seo['meta_description'], $seo['schema']['description']);
    }

    /**
     * Test TranslationService correctly translates when language is different from canonical.
     */
    public function test_translation_service_translates_when_different_language(): void
    {
        $translationService = app(TranslationInterface::class);

        // Update pipeline language to 'hi' (Hindi)
        $this->pipeline->update(['language' => 'hi']);

        $context = new PipelineContext($this->run, $this->pipeline);
        $context->title = 'Original English Title';
        $context->generatedContent = 'Original English Content.';

        $context = $translationService->handle($context);

        $this->assertFalse($context->hasErrors());

        // Verify original content is stored in metadata
        $this->assertEquals('Original English Title', $context->metadata['canonical_title']);
        $this->assertEquals('Original English Content.', $context->metadata['canonical_content']);

        // Verify content and title are translated (simulated)
        $this->assertEquals('[Translated to hi]: Original English Title', $context->title);
        $this->assertEquals('[Translated to hi]: Original English Content.', $context->generatedContent);
    }

    /**
     * Test TranslationService does not translate when language is same as canonical.
     */
    public function test_translation_service_skips_when_same_language(): void
    {
        $translationService = app(TranslationInterface::class);

        // Pipeline language is 'en', which matches canonical default 'en'
        $context = new PipelineContext($this->run, $this->pipeline);
        $context->title = 'Original English Title';
        $context->generatedContent = 'Original English Content.';

        $context = $translationService->handle($context);

        $this->assertFalse($context->hasErrors());

        // Verify canonical copies are not created in metadata
        $this->assertArrayNotHasKey('canonical_title', $context->metadata);
        $this->assertArrayNotHasKey('canonical_content', $context->metadata);

        // Title and content should remain unchanged
        $this->assertEquals('Original English Title', $context->title);
        $this->assertEquals('Original English Content.', $context->generatedContent);
    }

    /**
     * Test configuration-driven architecture supports adding new languages without modifying logic.
     */
    public function test_translation_service_supports_custom_configured_languages(): void
    {
        $translationService = app(TranslationInterface::class);

        // Register a new language ('es') in config
        Config::set('pipeline.supported_languages', ['en', 'hi', 'es']);

        // Update pipeline language to 'es'
        $this->pipeline->update(['language' => 'es']);

        $context = new PipelineContext($this->run, $this->pipeline);
        $context->title = 'English Title';
        $context->generatedContent = 'English Content.';

        $context = $translationService->handle($context);

        $this->assertFalse($context->hasErrors());

        // Verify it was translated to 'es'
        $this->assertEquals('English Title', $context->metadata['canonical_title']);
        $this->assertEquals('English Content.', $context->metadata['canonical_content']);
        $this->assertEquals('[Translated to es]: English Title', $context->title);
        $this->assertEquals('[Translated to es]: English Content.', $context->generatedContent);
    }

    /**
     * Test TranslationService skips translation when language is not supported/configured.
     */
    public function test_translation_service_skips_unsupported_languages(): void
    {
        $translationService = app(TranslationInterface::class);

        // Update pipeline language to 'fr' (French) which is not in the default supported list
        $this->pipeline->update(['language' => 'fr']);

        $context = new PipelineContext($this->run, $this->pipeline);
        $context->title = 'English Title';
        $context->generatedContent = 'English Content.';

        $context = $translationService->handle($context);

        $this->assertFalse($context->hasErrors());

        // Verify no translation occurred and original title/content remain
        $this->assertArrayNotHasKey('canonical_title', $context->metadata);
        $this->assertEquals('English Title', $context->title);
        $this->assertEquals('English Content.', $context->generatedContent);
    }
}
