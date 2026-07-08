<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\ContentPipeline\DTOs\PipelineContext;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\SiteManager\Models\Site;
use App\Modules\TopicManager\Models\Topic;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\MediaManager\Models\MediaItem;
use App\Modules\MediaManager\Services\MediaPreparationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaServiceEnhancementTest extends TestCase
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
            'generation_type' => 'article',
            'is_active' => true,
        ]);

        $this->run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'processing',
        ]);
    }

    /**
     * Test media preparation structures media_specs correctly in context metadata,
     * performs image generation, passes alt/caption to metadata, and updates status.
     */
    public function test_media_preparation_structures_and_generates_correctly(): void
    {
        Storage::fake('public');

        $dummyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

        Http::fake([
            'https://image.pollinations.ai/*' => Http::response($dummyPng, 200, [
                'Content-Type' => 'image/png',
                'Content-Length' => (string) strlen($dummyPng),
            ]),
        ]);

        $preparator = app(MediaPreparationService::class);
        $context = new PipelineContext($this->run, $this->pipeline);
        $context->resolvedTopic = 'Laravel 12 Features';
        $context->title = 'Article: Laravel 12 Features';
        $context->generatedContent = "# Laravel 12\n\nThis is a block placeholder:\n\n<!-- image-placeholder: prompt=\"laravel 12 logo\" alt=\"Laravel Logo\" caption=\"The new logo\" -->\n\nThis is an inline placeholder: <!-- image-placeholder: prompt=\"php code snippet\" alt=\"PHP Code\" caption=\"PHP 8.2+ snippet\" -->\n\nEnd.";

        // Run the service
        $context = $preparator->handle($context);

        $this->assertFalse($context->hasErrors());

        // 1. Verify media specifications schema is correctly built
        $this->assertArrayHasKey('media_specs', $context->metadata);
        $mediaSpecs = $context->metadata['media_specs'];

        $this->assertArrayHasKey('images', $mediaSpecs);
        $this->assertArrayHasKey('videos', $mediaSpecs);
        $this->assertArrayHasKey('audio', $mediaSpecs);
        $this->assertArrayHasKey('infographics', $mediaSpecs);

        // Videos, audio, infographics hooks are empty arrays
        $this->assertIsArray($mediaSpecs['videos']);
        $this->assertEmpty($mediaSpecs['videos']);
        $this->assertIsArray($mediaSpecs['audio']);
        $this->assertEmpty($mediaSpecs['audio']);
        $this->assertIsArray($mediaSpecs['infographics']);
        $this->assertEmpty($mediaSpecs['infographics']);

        // Check images layout
        $this->assertArrayHasKey('featured', $mediaSpecs['images']);
        $this->assertArrayHasKey('inline', $mediaSpecs['images']);

        // 2. Verify featured image details
        $featured = $mediaSpecs['images']['featured'];
        $this->assertEquals('A professional and high-quality featured image representing: Laravel 12 Features', $featured['prompt']);
        $this->assertEquals('Article: Laravel 12 Features', $featured['alt']);
        $this->assertEquals('Laravel 12 Features', $featured['caption']);
        $this->assertEquals('generated', $featured['status']);

        // 3. Verify inline image details
        $inline = $mediaSpecs['images']['inline'];
        $this->assertCount(2, $inline);

        $this->assertEquals('laravel 12 logo', $inline[0]['prompt']);
        $this->assertEquals('Laravel Logo', $inline[0]['alt']);
        $this->assertEquals('The new logo', $inline[0]['caption']);
        $this->assertEquals('generated', $inline[0]['status']);

        $this->assertEquals('php code snippet', $inline[1]['prompt']);
        $this->assertEquals('PHP Code', $inline[1]['alt']);
        $this->assertEquals('PHP 8.2+ snippet', $inline[1]['caption']);
        $this->assertEquals('generated', $inline[1]['status']);

        // 4. Verify media items are saved to database and metadata includes alt, caption, prompt
        $mediaItems = MediaItem::all();
        // 1 featured image + 2 inline images = 3
        $this->assertCount(3, $mediaItems);

        foreach ($mediaItems as $item) {
            $this->assertNotNull($item->filename);
            $this->assertNotNull($item->filepath);
            Storage::disk('public')->assertExists($item->filepath);

            $this->assertArrayHasKey('alt', $item->metadata);
            $this->assertArrayHasKey('caption', $item->metadata);
            $this->assertArrayHasKey('prompt', $item->metadata);
            $this->assertNotEmpty($item->metadata['alt']);
            $this->assertNotEmpty($item->metadata['caption']);
            $this->assertNotEmpty($item->metadata['prompt']);
        }

        // Check HTML contains the substituted image tags
        $html = $context->generatedContent;
        // Featured image prepended
        $this->assertStringContainsString('class="post-featured-image"', $html);
        // Inline and block images replaced
        $this->assertStringContainsString('<figure class="wp-block-image size-large"', $html);
        $this->assertStringContainsString('alt="Laravel Logo"', $html);
        $this->assertStringContainsString('alt="PHP Code"', $html);
        $this->assertStringContainsString('The new logo', $html);
        $this->assertStringContainsString('PHP 8.2+ snippet', $html);
    }
}
