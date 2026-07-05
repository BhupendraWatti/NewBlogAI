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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WordPressPublishingTest extends TestCase
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
            'name' => 'Web Development',
            'category' => 'Tech',
            'status' => 'active',
            'generation_frequency' => 'daily',
        ]);

        $this->article = GeneratedContent::create([
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'title' => 'Mock Article Title',
            'content' => 'Mock article body content.',
            'status' => 'approved',
        ]);
    }

    public function test_user_can_queue_article_publishing(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/articles/{$this->article->id}/publish", [
                'site_id' => $this->site->id,
                'wp_status' => 'publish',
            ]);

        $response->assertStatus(202)
            ->assertJsonPath('log.status', 'pending');

        $this->assertDatabaseHas('publishing_logs', [
            'generated_content_id' => $this->article->id,
            'site_id' => $this->site->id,
            'wp_status' => 'publish',
            'status' => 'pending',
        ]);

        Queue::assertPushed(PublishPostJob::class);
    }

    public function test_publishing_job_execution_success(): void
    {
        // Fake WordPress Post Creation response
        Http::fake([
            'https://mockwp.com/wp-json/newsblogify/v1/publish' => Http::response([], 404),
            'https://mockwp.com/wp-json/wp/v2/posts' => Http::response([
                'id' => 4521,
                'link' => 'https://mockwp.com/mock-article-title/',
            ], 201),
        ]);

        $log = PublishingLog::create([
            'generated_content_id' => $this->article->id,
            'site_id' => $this->site->id,
            'user_id' => $this->admin->id,
            'status' => 'pending',
            'wp_status' => 'publish',
        ]);

        $job = new PublishPostJob($log->id);
        $job->handle(resolve(PublishingService::class));

        $log->refresh();
        $this->assertEquals('completed', $log->status);
        $this->assertEquals(4521, $log->wp_post_id);
        $this->assertEquals('https://mockwp.com/mock-article-title/', $log->published_url);

        // Verify generated content status transitioned to published
        $this->assertEquals('published', $this->article->fresh()->status);
    }

    public function test_duplicate_publishing_prevention(): void
    {
        // Setup completed log
        PublishingLog::create([
            'generated_content_id' => $this->article->id,
            'site_id' => $this->site->id,
            'status' => 'completed',
        ]);

        // Attempting to publish again to same site
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/articles/{$this->article->id}/publish", [
                'site_id' => $this->site->id,
            ]);

        $response->assertStatus(422); // Throws validation / duplicate error
    }

    public function test_manual_post_status_sync_handles_deletion(): void
    {
        // Fake remote deletion (returns 404)
        Http::fake([
            'https://mockwp.com/wp-json/wp/v2/posts/4521' => Http::response([], 404),
        ]);

        $log = PublishingLog::create([
            'generated_content_id' => $this->article->id,
            'site_id' => $this->site->id,
            'status' => 'completed',
            'wp_post_id' => 4521,
            'published_url' => 'https://mockwp.com/mock-article-title/',
        ]);

        // Trigger manual status sync via API
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/publishing/logs/{$log->id}/sync");

        $response->assertStatus(200);

        $log->refresh();
        $this->assertEquals('failed', $log->status);
        $this->assertEquals('Post was deleted or unpublished from WordPress.', $log->error_message);

        // Assert content status reverted to draft
        $this->assertEquals('draft', $this->article->fresh()->status);
    }

    public function test_queued_publishing_can_be_cancelled(): void
    {
        $log = PublishingLog::create([
            'generated_content_id' => $this->article->id,
            'site_id' => $this->site->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/publishing/logs/{$log->id}/cancel");

        $response->assertStatus(200);
        $this->assertEquals('cancelled', $log->fresh()->status);

        // Content status reverts to approved
        $this->assertEquals('approved', $this->article->fresh()->status);
    }
}
