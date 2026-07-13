<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\ContentPipeline\DTOs\PipelineContext;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\MediaManager\Models\MediaItem;
use App\Modules\MediaManager\Services\ContentPostProcessor;
use App\Modules\MediaManager\Services\ImageGeneratorService;
use App\Modules\MediaManager\Services\MediaPreparationService;
use App\Modules\SiteManager\Models\Site;
use App\Modules\TopicManager\Models\Topic;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\SystemSettings\Models\Setting;
use App\Modules\SystemSettings\Services\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageGenerationToggleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected SystemSettingsService $settingsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsService = app(SystemSettingsService::class);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->role = 2; // Admin
        $this->admin->save();

        Cache::flush();
    }

    /**
     * Test the API correctly accepts and stores the toggle in the database.
     */
    public function test_settings_api_toggles_enable_image_generation(): void
    {
        // 1. Initially it should default to true (or return the default)
        $response = $this->actingAs($this->admin)->getJson('/api/v1/settings');
        $response->assertStatus(200);
        $this->assertTrue((bool) $response->json('settings.enable_image_generation'));

        // 2. Set toggle to false (using false value)
        $response = $this->actingAs($this->admin)->postJson('/api/v1/settings', [
            'enable_image_generation' => false,
        ]);
        $response->assertStatus(200);
        $this->assertFalse((bool) $response->json('settings.enable_image_generation'));

        // Verify stored in DB
        $this->assertDatabaseHas('settings', [
            'key' => 'enable_image_generation',
            'value' => json_encode(false),
        ]);

        // Get value from service directly to verify cache was cleared and new value fetched
        $this->assertFalse($this->settingsService->get('enable_image_generation'));

        // 3. Set toggle back to true (using 1 as integer representation of boolean)
        $response = $this->actingAs($this->admin)->postJson('/api/v1/settings', [
            'enable_image_generation' => 1,
        ]);
        $response->assertStatus(200);
        $this->assertTrue((bool) $response->json('settings.enable_image_generation'));

        // Verify stored in DB as true (json encoded)
        $this->assertDatabaseHas('settings', [
            'key' => 'enable_image_generation',
            'value' => json_encode(1),
        ]);

        // Since get() casts to bool or returns what was set, verify the truthiness
        $this->assertTrue((bool) $this->settingsService->get('enable_image_generation'));

        // 4. Set toggle to 0 (as integer representation of false)
        $response = $this->actingAs($this->admin)->postJson('/api/v1/settings', [
            'enable_image_generation' => 0,
        ]);
        $response->assertStatus(200);
        $this->assertFalse((bool) $response->json('settings.enable_image_generation'));

        $this->assertFalse((bool) $this->settingsService->get('enable_image_generation'));
    }

    /**
     * Test that ImageGeneratorService throws exception when disabled.
     */
    public function test_image_generator_service_throws_when_disabled(): void
    {
        // Turn off image generation
        $this->settingsService->set('enable_image_generation', false);

        $service = app(ImageGeneratorService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Image generation is disabled in system settings.');

        $service->generateAndStore('test prompt');
    }

    /**
     * Test that when disabled, ContentPostProcessor skips image generation.
     */
    public function test_content_post_processor_skips_generation_when_disabled(): void
    {
        Storage::fake('public');

        // Turn off image generation
        $this->settingsService->set('enable_image_generation', false);

        // Track if any HTTP requests are sent
        Http::fake([
            '*' => function () {
                $this->fail('No HTTP requests should be sent when image generation is disabled.');
            }
        ]);

        $site = Site::create([
            'domain_url' => 'https://example-blog.com',
            'api_key' => 'test-api-token',
            'is_active' => true,
        ]);

        $topic = Topic::create([
            'name' => 'Laravel Testing',
            'category' => 'Technology',
            'status' => 'active',
            'generation_frequency' => 'daily',
        ]);

        $contentInput = "# Header Title\n\n".
            'This is a paragraph with an inline image: '.
            "<!-- image-placeholder: prompt=\"cute dog\" alt=\"Cute Dog Alt\" caption=\"Cute Dog Caption\" -->\n\n".
            'And another paragraph with a simple placeholder: <!-- image-placeholder: simple prompt -->';

        $generatedContent = GeneratedContent::create([
            'site_id' => $site->id,
            'topic_id' => $topic->id,
            'title' => 'Sample Blog Post',
            'content' => $contentInput,
            'status' => 'draft',
            'metadata' => [],
        ]);

        $processor = app(ContentPostProcessor::class);

        // Run processor - it should NOT throw an exception and should complete successfully
        $processor->process($generatedContent);

        $generatedContent->refresh();

        // Check metadata has processed info but NO featured image
        $this->assertArrayHasKey('processed_at', $generatedContent->metadata);
        $this->assertArrayNotHasKey('featured_image_id', $generatedContent->metadata);
        $this->assertArrayNotHasKey('featured_image_url', $generatedContent->metadata);

        $html = $generatedContent->content;

        // Verify HTML converted headers
        $this->assertStringContainsString('<h1>Header Title</h1>', $html);

        // Verify NO image/figure tags were added, and placeholders were removed (replaced with empty string)
        $this->assertStringNotContainsString('<figure', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('cute dog', $html);
        $this->assertStringNotContainsString('simple prompt', $html);

        // Verify no MediaItem records were created
        $this->assertEquals(0, MediaItem::count());
    }

    /**
     * Test that when disabled, MediaPreparationService skips generation.
     */
    public function test_media_preparation_service_skips_generation_when_disabled(): void
    {
        Storage::fake('public');

        // Turn off image generation
        $this->settingsService->set('enable_image_generation', false);

        // Track if any HTTP requests are sent
        Http::fake([
            '*' => function () {
                $this->fail('No HTTP requests should be sent when image generation is disabled.');
            }
        ]);

        $site = Site::create([
            'domain_url' => 'https://example-test.com',
            'api_key' => 'test-token',
            'is_active' => true,
        ]);

        $topic = Topic::create([
            'name' => 'Laravel 12 Features',
            'category' => 'Tech',
            'language' => 'en',
            'status' => 'active',
            'generation_frequency' => 'daily',
        ]);

        $prompt = Prompt::create([
            'name' => 'Test Prompt',
            'prompt' => 'Write about {{topic}}',
            'category' => 'Tech',
            'status' => 'active',
        ]);

        $provider = AIProvider::create([
            'provider_key' => 'gemini',
            'name' => 'Google Gemini',
            'api_key' => 'some-api-key',
            'default_model' => 'gemini-2.5-flash',
            'is_enabled' => true,
        ]);

        $pipeline = ContentPipeline::create([
            'site_id' => $site->id,
            'topic_id' => $topic->id,
            'prompt_id' => $prompt->id,
            'ai_provider_id' => $provider->id,
            'language' => 'en',
            'generation_type' => 'article',
            'is_active' => true,
        ]);

        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'processing',
        ]);

        $preparator = app(MediaPreparationService::class);
        $context = new PipelineContext($run, $pipeline);
        $context->resolvedTopic = 'Laravel 12 Features';
        $context->title = 'Article: Laravel 12 Features';
        $context->generatedContent = "# Laravel 12\n\nThis is a block placeholder:\n\n<!-- image-placeholder: prompt=\"laravel 12 logo\" alt=\"Laravel Logo\" caption=\"The new logo\" -->\n\nThis is an inline placeholder: <!-- image-placeholder: prompt=\"php code snippet\" alt=\"PHP Code\" caption=\"PHP 8.2+ snippet\" -->\n\nEnd.";

        // Run the service
        $context = $preparator->handle($context);

        // Check pipeline completes without error
        $this->assertFalse($context->hasErrors());

        // Check media_specs in context metadata have 'disabled' status
        $this->assertArrayHasKey('media_specs', $context->metadata);
        $mediaSpecs = $context->metadata['media_specs'];

        $this->assertEquals('disabled', $mediaSpecs['images']['featured']['status']);
        $this->assertEquals('disabled', $mediaSpecs['images']['inline'][0]['status']);
        $this->assertEquals('disabled', $mediaSpecs['images']['inline'][1]['status']);

        // Check no MediaItem was created in the database
        $this->assertEquals(0, MediaItem::count());

        // Check generatedContent does not have images/figure tags
        $html = $context->generatedContent;
        $this->assertStringNotContainsString('<figure', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('class="post-featured-image"', $html);

        // The placeholders are replaced with comment indicating failed/disabled generation
        $this->assertStringContainsString('<!-- image-placeholder-failed: laravel 12 logo -->', $html);
        $this->assertStringContainsString('<!-- image-placeholder-failed: php code snippet -->', $html);
    }
}
