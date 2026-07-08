<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\ContentGeneration\Services\WorkflowService;
use App\Modules\ContentGeneration\Exceptions\InvalidWorkflowTransitionException;
use App\Modules\Publishing\Jobs\PublishPostJob;
use App\Modules\Publishing\Models\PublishingLog;
use App\Modules\Publishing\Services\PublishingService;
use App\Modules\SiteManager\Models\Site;
use App\Modules\TopicManager\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EditorialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Site $site;
    protected Topic $topic;
    protected WorkflowService $workflowService;

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
            'name' => 'Tech and Dev',
            'category' => 'Technology',
            'status' => 'active',
            'generation_frequency' => 'daily',
        ]);

        $this->workflowService = resolve(WorkflowService::class);
    }

    /**
     * Test valid transitions succeed and record history.
     */
    public function test_valid_transitions_succeed_and_record_history(): void
    {
        $article = GeneratedContent::create([
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'title' => 'Initial Title',
            'content' => 'Content here.',
            'status' => 'generated',
        ]);

        // 1. generated -> pending_review
        $this->workflowService->transitionTo($article, 'pending_review', $this->admin->id);
        $article->refresh();
        $this->assertEquals('pending_review', $article->status);

        // 2. pending_review -> approved
        $this->workflowService->transitionTo($article, 'approved', $this->admin->id);
        $article->refresh();
        $this->assertEquals('approved', $article->status);

        // 3. approved -> scheduled
        $this->workflowService->transitionTo($article, 'scheduled', $this->admin->id);
        $article->refresh();
        $this->assertEquals('scheduled', $article->status);

        // 4. scheduled -> published
        $this->workflowService->transitionTo($article, 'published', $this->admin->id);
        $article->refresh();
        $this->assertEquals('published', $article->status);

        // Verify history in metadata
        $history = $article->metadata['workflow_history'] ?? [];
        $this->assertCount(4, $history);

        $this->assertEquals('generated', $history[0]['from_status']);
        $this->assertEquals('pending_review', $history[0]['to_status']);
        $this->assertEquals($this->admin->id, $history[0]['user_id']);

        $this->assertEquals('pending_review', $history[1]['from_status']);
        $this->assertEquals('approved', $history[1]['to_status']);

        $this->assertEquals('approved', $history[2]['from_status']);
        $this->assertEquals('scheduled', $history[2]['to_status']);

        $this->assertEquals('scheduled', $history[3]['from_status']);
        $this->assertEquals('published', $history[3]['to_status']);
    }

    /**
     * Test invalid transition throws exception.
     */
    public function test_invalid_transitions_throw_exception(): void
    {
        $article = GeneratedContent::create([
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'title' => 'Initial Title',
            'content' => 'Content here.',
            'status' => 'rejected',
        ]);

        // Try transitioning directly from rejected to published (invalid)
        $this->expectException(InvalidWorkflowTransitionException::class);
        $this->workflowService->transitionTo($article, 'published', $this->admin->id);
    }

    /**
     * Test integration with manual queue publishing flow.
     */
    public function test_publishing_service_queue_publish_transitions_status(): void
    {
        Queue::fake();

        $article = GeneratedContent::create([
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'title' => 'Initial Title',
            'content' => 'Content here.',
            'status' => 'draft',
        ]);

        $publishingService = resolve(PublishingService::class);
        $publishingService->queuePublish($article->id, [
            'site_id' => $this->site->id,
            'wp_status' => 'publish',
        ], $this->admin->id);

        // Assert article transitioned draft -> pending_review
        $article->refresh();
        $this->assertEquals('pending_review', $article->status);

        $history = $article->metadata['workflow_history'] ?? [];
        $this->assertCount(1, $history);
        $this->assertEquals('draft', $history[0]['from_status']);
        $this->assertEquals('pending_review', $history[0]['to_status']);
    }

    /**
     * Test integration with publishing job execution (scheduled vs immediate).
     */
    public function test_execute_publish_transitions_to_published_or_scheduled(): void
    {
        Http::fake([
            'https://mockwp.com/wp-json/newsblogify/v1/publish' => Http::response([], 404),
            'https://mockwp.com/wp-json/wp/v2/posts' => Http::response([
                'id' => 9999,
                'link' => 'https://mockwp.com/post-link/',
            ], 201),
        ]);

        $publishingService = resolve(PublishingService::class);

        // Case 1: Immediate publishing
        $article1 = GeneratedContent::create([
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'title' => 'Article 1',
            'content' => 'Content 1',
            'status' => 'approved',
        ]);

        $log1 = PublishingLog::create([
            'generated_content_id' => $article1->id,
            'site_id' => $this->site->id,
            'user_id' => $this->admin->id,
            'status' => 'pending',
            'wp_status' => 'publish',
        ]);

        $publishingService->executePublish($log1);

        $article1->refresh();
        $this->assertEquals('published', $article1->status);
        $this->assertEquals('approved', $article1->metadata['workflow_history'][0]['from_status']);
        $this->assertEquals('published', $article1->metadata['workflow_history'][0]['to_status']);

        // Case 2: Scheduled publishing
        $article2 = GeneratedContent::create([
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'title' => 'Article 2',
            'content' => 'Content 2',
            'status' => 'approved',
        ]);

        $log2 = PublishingLog::create([
            'generated_content_id' => $article2->id,
            'site_id' => $this->site->id,
            'user_id' => $this->admin->id,
            'status' => 'pending',
            'wp_status' => 'future',
            'scheduled_at' => now()->addDays(5),
        ]);

        $publishingService->executePublish($log2);

        $article2->refresh();
        $this->assertEquals('scheduled', $article2->status);
        $this->assertEquals('approved', $article2->metadata['workflow_history'][0]['from_status']);
        $this->assertEquals('scheduled', $article2->metadata['workflow_history'][0]['to_status']);
    }

    /**
     * Test sync status handles deletion reverting to draft.
     */
    public function test_sync_status_transitions_deleted_to_draft(): void
    {
        Http::fake([
            'https://mockwp.com/wp-json/wp/v2/posts/9999' => Http::response([], 404),
        ]);

        $article = GeneratedContent::create([
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'title' => 'Synced Article',
            'content' => 'Content',
            'status' => 'published',
        ]);

        $log = PublishingLog::create([
            'generated_content_id' => $article->id,
            'site_id' => $this->site->id,
            'status' => 'completed',
            'wp_post_id' => 9999,
        ]);

        $publishingService = resolve(PublishingService::class);
        $publishingService->syncPostStatus($log);

        $article->refresh();
        $this->assertEquals('draft', $article->status);
        $this->assertEquals('published', $article->metadata['workflow_history'][0]['from_status']);
        $this->assertEquals('draft', $article->metadata['workflow_history'][0]['to_status']);
    }

    /**
     * Test cancelling publishing transitions back to approved.
     */
    public function test_cancel_publishing_transitions_to_approved(): void
    {
        $article = GeneratedContent::create([
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'title' => 'To Cancel',
            'content' => 'Content',
            'status' => 'pending_review',
        ]);

        $log = PublishingLog::create([
            'generated_content_id' => $article->id,
            'site_id' => $this->site->id,
            'status' => 'pending',
        ]);

        $publishingService = resolve(PublishingService::class);
        $publishingService->cancelPublish($log);

        $article->refresh();
        $this->assertEquals('approved', $article->status);
        $this->assertEquals('pending_review', $article->metadata['workflow_history'][0]['from_status']);
        $this->assertEquals('approved', $article->metadata['workflow_history'][0]['to_status']);
    }

    /**
     * Test job failure transitions to failed then draft.
     */
    public function test_job_failure_transitions_to_failed_then_draft(): void
    {
        Http::fake([
            'https://mockwp.com/wp-json/newsblogify/v1/publish' => Http::response([], 500),
            'https://mockwp.com/wp-json/wp/v2/posts' => Http::response([], 500),
        ]);

        $article = GeneratedContent::create([
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'title' => 'To Fail',
            'content' => 'Content',
            'status' => 'pending_review',
        ]);

        $log = PublishingLog::create([
            'generated_content_id' => $article->id,
            'site_id' => $this->site->id,
            'user_id' => $this->admin->id,
            'status' => 'pending',
            'wp_status' => 'publish',
        ]);

        $job = new PublishPostJob($log->id);
        $job->tries = 1;
        
        try {
            $job->handle(resolve(PublishingService::class));
        } catch (\Exception $e) {
            // expected fail
        }

        $article->refresh();
        $this->assertEquals('draft', $article->status);

        $history = $article->metadata['workflow_history'] ?? [];
        $this->assertCount(2, $history);
        
        // 1st transition: pending_review -> failed
        $this->assertEquals('pending_review', $history[0]['from_status']);
        $this->assertEquals('failed', $history[0]['to_status']);

        // 2nd transition: failed -> draft
        $this->assertEquals('failed', $history[1]['from_status']);
        $this->assertEquals('draft', $history[1]['to_status']);
    }
}
