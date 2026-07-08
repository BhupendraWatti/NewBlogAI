<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\Publishing\Jobs\PublishPostJob;
use App\Modules\Publishing\Models\PublishingLog;
use App\Modules\Publishing\Services\PublishingService;
use App\Modules\SiteManager\Models\Site;
use App\Modules\TopicManager\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WordPressPublishingEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Site $site;
    protected Topic $topic;
    protected GeneratedContent $article;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->role = 2; // Admin
        $this->admin->save();

        $this->site = Site::create([
            'domain_url' => 'https://mockwp.com',
            'api_key' => 'secret-api-token',
            'is_active' => true,
        ]);

        $this->topic = Topic::create([
            'name' => 'Laravel Development',
            'category' => 'Web Development',
            'status' => 'active',
            'generation_frequency' => 'daily',
        ]);

        $this->article = GeneratedContent::create([
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'title' => 'Advanced Features in Laravel 12',
            'content' => '<p>Laravel 12 includes many advanced features...</p>',
            'status' => 'approved',
            'metadata' => [
                'featured_image_url' => 'https://mockwp.com/images/featured.jpg',
                'seo' => [
                    'title' => 'Laravel 12 Advanced Guide',
                    'meta_description' => 'A guide covering advanced features in Laravel 12.',
                    'slug' => 'laravel-12-advanced-guide',
                    'focus_keywords' => ['laravel 12', 'php framework', 'advanced laravel'],
                ]
            ],
        ]);
    }

    public function test_advanced_fields_passed_correctly_to_wordpress_plugin(): void
    {
        Http::fake([
            'https://mockwp.com/wp-json/newsblogify/v1/publish' => Http::response([
                'status' => 'success',
                'wp_post_id' => 9999,
                'post_url' => 'https://mockwp.com/laravel-12-advanced-guide/',
            ], 200),
        ]);

        $log = PublishingLog::create([
            'generated_content_id' => $this->article->id,
            'site_id' => $this->site->id,
            'user_id' => $this->admin->id,
            'status' => 'pending',
            'wp_status' => 'publish',
        ]);

        $publishingService = resolve(PublishingService::class);
        $publishingService->executePublish($log);

        Http::assertSent(function (Request $request) {
            $this->assertEquals('https://mockwp.com/wp-json/newsblogify/v1/publish', $request->url());
            
            $payload = $request->data();
            
            // Verify basic fields
            $this->assertEquals('Advanced Features in Laravel 12', $payload['title']);
            $this->assertEquals('<p>Laravel 12 includes many advanced features...</p>', $payload['content']);
            $this->assertEquals('publish', $payload['status']);
            
            // Verify resolved categories
            $this->assertEquals(['Web Development'], $payload['categories']);
            
            // Verify resolved tags
            $this->assertEquals(['laravel 12', 'php framework', 'advanced laravel'], $payload['tags']);
            
            // Verify featured image URL
            $this->assertEquals('https://mockwp.com/images/featured.jpg', $payload['featured_image_url']);
            
            // Verify slug
            $this->assertEquals('laravel-12-advanced-guide', $payload['slug']);
            
            // Verify meta array with Yoast and RankMath fields
            $meta = $payload['meta'];
            $this->assertEquals('Laravel 12 Advanced Guide', $meta['_yoast_wpseo_title']);
            $this->assertEquals('Laravel 12 Advanced Guide', $meta['rank_math_title']);
            $this->assertEquals('A guide covering advanced features in Laravel 12.', $meta['_yoast_wpseo_metadesc']);
            $this->assertEquals('A guide covering advanced features in Laravel 12.', $meta['rank_math_description']);
            $this->assertEquals('laravel 12, php framework, advanced laravel', $meta['_yoast_wpseo_focuskw']);
            $this->assertEquals('laravel 12, php framework, advanced laravel', $meta['rank_math_focus_keyword']);

            return true;
        });

        $log->refresh();
        $this->assertEquals('completed', $log->status);
        $this->assertEquals(9999, $log->wp_post_id);
        $this->assertEquals('https://mockwp.com/laravel-12-advanced-guide/', $log->published_url);
    }

    public function test_idempotency_behavior_on_retrying_job(): void
    {
        // First execution succeeds and returns post ID 9999
        Http::fake([
            'https://mockwp.com/wp-json/newsblogify/v1/publish' => Http::sequence()
                ->push([
                    'status' => 'success',
                    'wp_post_id' => 9999,
                    'post_url' => 'https://mockwp.com/laravel-12-advanced-guide/',
                ], 200)
                ->push([
                    'status' => 'success',
                    'wp_post_id' => 9999,
                    'post_url' => 'https://mockwp.com/laravel-12-advanced-guide/',
                ], 200),
        ]);

        $log = PublishingLog::create([
            'generated_content_id' => $this->article->id,
            'site_id' => $this->site->id,
            'user_id' => $this->admin->id,
            'status' => 'pending',
            'wp_status' => 'publish',
        ]);

        $publishingService = resolve(PublishingService::class);
        
        // Execute first time
        $publishingService->executePublish($log);
        $log->refresh();
        $this->assertEquals(9999, $log->wp_post_id);
        $this->assertEquals('completed', $log->status);

        // Execute retry (using the same log, simulating a retry or duplicate job run)
        $log->update(['status' => 'pending']);
        
        $publishingService->executePublish($log);
        $log->refresh();
        
        $this->assertEquals(9999, $log->wp_post_id);
        $this->assertEquals('completed', $log->status);
    }
}
