<?php

namespace Tests\Feature;

use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\MediaManager\Drivers\PollinationsDriver;
use App\Modules\MediaManager\Models\MediaItem;
use App\Modules\MediaManager\Services\ContentPostProcessor;
use App\Modules\MediaManager\Services\ImageGeneratorService;
use App\Modules\SiteManager\Models\Site;
use App\Modules\TopicManager\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaManagerTest extends TestCase
{
    use RefreshDatabase;

    protected Site $site;

    protected Topic $topic;

    protected ImageGeneratorService $imageGeneratorService;

    protected ContentPostProcessor $contentPostProcessor;

    protected function setUp(): void
    {
        parent::setUp();

        // Resolve services from container
        $this->imageGeneratorService = app(ImageGeneratorService::class);
        $this->contentPostProcessor = app(ContentPostProcessor::class);

        // Setup common database requirements
        $this->site = Site::create([
            'domain_url' => 'https://example-blog.com',
            'api_key' => 'test-api-token',
            'is_active' => true,
        ]);

        $this->topic = Topic::create([
            'name' => 'Laravel Testing',
            'category' => 'Technology',
            'status' => 'active',
            'generation_frequency' => 'daily',
        ]);
    }

    /**
     * Test 1: ImageGeneratorService resolves pollinations driver and stores MediaItem.
     */
    public function test_image_generator_service_resolves_pollinations_driver_and_stores_media_item(): void
    {
        // 1. Verify driver resolution
        $driver = $this->imageGeneratorService->getDriver('pollinations');
        $this->assertInstanceOf(PollinationsDriver::class, $driver);

        // 2. Mock storage and HTTP calls
        Storage::fake('public');

        // 1x1 pixel transparent PNG in base64
        $dummyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

        Http::fake([
            'https://image.pollinations.ai/*' => function ($request) use ($dummyPng) {
                if ($request->method() === 'HEAD') {
                    return Http::response('', 200, ['Content-Length' => (string) strlen($dummyPng)]);
                }

                return Http::response($dummyPng, 200, [
                    'Content-Type' => 'image/png',
                ]);
            },
        ]);

        $prompt = 'A beautiful coding space';

        // 3. Call generateAndStore
        $mediaItem = $this->imageGeneratorService->generateAndStore($prompt, ['driver' => 'pollinations']);

        // 4. Assertions on returned MediaItem
        $this->assertInstanceOf(MediaItem::class, $mediaItem);
        $this->assertEquals('pollinations', $mediaItem->driver);
        $this->assertEquals($prompt, $mediaItem->prompt);
        $this->assertNotNull($mediaItem->filename);
        $this->assertNotNull($mediaItem->filepath);

        // Verify stored in Database
        $this->assertDatabaseHas('media_items', [
            'id' => $mediaItem->id,
            'driver' => 'pollinations',
            'prompt' => $prompt,
        ]);

        // Verify file is written to public disk
        Storage::disk('public')->assertExists($mediaItem->filepath);
    }

    /**
     * Test 2: ContentPostProcessor scans and replaces <!-- image-placeholder --> tags
     * with figure blocks and converts the remaining text to HTML.
     */
    public function test_content_post_processor_scans_and_replaces_placeholders_and_converts_markdown(): void
    {
        Storage::fake('public');
        $dummyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

        Http::fake([
            '*' => function ($request) use ($dummyPng) {
                if ($request->method() === 'HEAD') {
                    return Http::response('', 200, ['Content-Length' => (string) strlen($dummyPng)]);
                }

                return Http::response($dummyPng, 200, [
                    'Content-Type' => 'image/png',
                ]);
            },
        ]);

        $contentInput = "# Header Title\n\n".
            'This is a paragraph with an inline image: '.
            "<!-- image-placeholder: prompt=\"cute dog\" alt=\"Cute Dog Alt\" caption=\"Cute Dog Caption\" -->\n\n".
            'And another paragraph with a simple placeholder: <!-- image-placeholder: simple prompt -->';

        $generatedContent = GeneratedContent::create([
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'title' => 'Sample Blog Post',
            'content' => $contentInput,
            'status' => 'draft',
            'metadata' => [],
        ]);

        // Run processor
        $this->contentPostProcessor->process($generatedContent);

        $generatedContent->refresh();

        // 1. Check metadata has processed info
        $this->assertArrayHasKey('processed_at', $generatedContent->metadata);
        $this->assertArrayHasKey('featured_image_id', $generatedContent->metadata);
        $this->assertArrayHasKey('featured_image_url', $generatedContent->metadata);

        $html = $generatedContent->content;

        // 2. Check header converted to HTML
        $this->assertStringContainsString('<h1>Header Title</h1>', $html);

        // 3. Check inline image-placeholder tags replaced with figure blocks
        $this->assertStringContainsString('<figure class="wp-block-image size-large"', $html);
        $this->assertStringContainsString('alt="Cute Dog Alt"', $html);
        $this->assertStringContainsString('<figcaption style="font-style: italic; text-align: center; font-size: 0.9em; margin-top: 5px;">Cute Dog Caption</figcaption>', $html);

        // 4. Check simple prompt placeholder replacement
        $this->assertStringContainsString('alt="simple prompt"', $html);

        // 5. Check that featured image is prepended at the beginning
        $this->assertStringStartsWith('<div class="post-featured-image"', $html);
    }

    /**
     * Test 3: Link scheme validation in ContentPostProcessor::parseInlineMarkdown()
     * rejects malicious protocols to prevent Stored XSS.
     */
    public function test_link_scheme_validation_rejects_malicious_protocols(): void
    {
        // Since parseInlineMarkdown is protected, we test via convertMarkdownToHtml

        // Malicious javascript link
        $maliciousJs = '[Click Me](javascript:alert(1))';
        $htmlJs = $this->contentPostProcessor->convertMarkdownToHtml($maliciousJs);
        $this->assertStringNotContainsString('<a href=', $htmlJs);
        $this->assertStringContainsString('Click Me', $htmlJs);

        // Malicious data URI link
        $maliciousData = '[Click Me](data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==)';
        $htmlData = $this->contentPostProcessor->convertMarkdownToHtml($maliciousData);
        $this->assertStringNotContainsString('<a href=', $htmlData);
        $this->assertStringContainsString('Click Me', $htmlData);

        // Valid http/https links should pass
        $validHttp = '[Google](https://google.com)';
        $htmlValid = $this->contentPostProcessor->convertMarkdownToHtml($validHttp);
        $this->assertStringContainsString('<a href="https://google.com">Google</a>', $htmlValid);

        // Relative path or no scheme link should be rejected/sanitized if it's evaluated as non-http/https
        // Let's test a schema-less link format
        $noScheme = '[Local Link](index.html)';
        $htmlNoScheme = $this->contentPostProcessor->convertMarkdownToHtml($noScheme);
        $this->assertStringContainsString('<a href="index.html">Local Link</a>', $htmlNoScheme);
    }

    /**
     * Test 4: ContentPostProcessor is idempotent.
     */
    public function test_content_post_processor_is_idempotent(): void
    {
        Storage::fake('public');
        $dummyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

        Http::fake([
            '*' => function ($request) use ($dummyPng) {
                if ($request->method() === 'HEAD') {
                    return Http::response('', 200, ['Content-Length' => (string) strlen($dummyPng)]);
                }

                return Http::response($dummyPng, 200, [
                    'Content-Type' => 'image/png',
                ]);
            },
        ]);

        $contentInput = "# Title\n\n<!-- image-placeholder: prompt=\"test\" -->\n\nContent body.";

        $generatedContent = GeneratedContent::create([
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'title' => 'Idempotency Test',
            'content' => $contentInput,
            'status' => 'draft',
            'metadata' => [],
        ]);

        // First execution
        $this->contentPostProcessor->process($generatedContent);
        $generatedContent->refresh();

        $firstHtml = $generatedContent->content;
        $firstMetadata = $generatedContent->metadata;
        $this->assertNotNull($firstHtml);
        $this->assertArrayHasKey('processed_at', $firstMetadata);

        // Reset Http fake count/log to see if it calls image generator again
        Http::fake([
            '*' => function () {
                $this->fail('HTTP request should not be made during idempotent call.');
            },
        ]);

        // Second execution
        $this->contentPostProcessor->process($generatedContent);
        $generatedContent->refresh();

        $secondHtml = $generatedContent->content;
        $secondMetadata = $generatedContent->metadata;

        // Verify content and metadata are completely unchanged
        $this->assertEquals($firstHtml, $secondHtml);
        $this->assertEquals($firstMetadata, $secondMetadata);
    }
}
