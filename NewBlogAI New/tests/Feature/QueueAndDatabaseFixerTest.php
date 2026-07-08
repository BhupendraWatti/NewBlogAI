<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Key;
use App\Modules\CustomerManager\Models\Customer;
use App\Modules\CustomerManager\Models\Workspace;
use App\Modules\SiteManager\Models\Site;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\ContentPipeline\Jobs\ProcessPipelineJob;
use App\Modules\SiteManager\Jobs\SyncSiteDataJob;
use App\Modules\Publishing\Jobs\PublishPostJob;
use App\Modules\Publishing\Models\PublishingLog;
use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\TopicManager\Models\Topic;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\AIProviderManager\Models\AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueAndDatabaseFixerTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_and_workspace_relationship()
    {
        $customer = Customer::create([
            'company_name' => 'Test Company',
            'owner_name' => 'Owner',
            'email' => 'owner@test.com',
            'status' => 'active',
        ]);

        $workspace = Workspace::create([
            'name' => 'Test Workspace',
            'customer_id' => $customer->id,
        ]);

        $site = Site::create([
            'customer_id' => $customer->id,
            'domain_url' => 'https://example.com',
            'is_active' => true,
            'workspace_id' => $workspace->id,
        ]);

        // Assert relationships
        $this->assertEquals($workspace->id, $site->workspace->id);
        $this->assertTrue($workspace->sites->contains($site));
    }

    public function test_process_pipeline_job_rethrows_exception_on_failure()
    {
        $customer = Customer::create([
            'company_name' => 'Test Company',
            'owner_name' => 'Owner',
            'email' => 'owner@test.com',
            'status' => 'active',
        ]);

        $site = Site::create([
            'customer_id' => $customer->id,
            'domain_url' => 'https://example.com',
            'is_active' => true,
        ]);

        $topic = Topic::create([
            'name' => 'Test Topic',
            'status' => 'active',
        ]);

        $prompt = Prompt::create([
            'name' => 'Test Prompt',
            'prompt' => 'Test Prompt',
            'category' => 'Test',
            'status' => 'active',
        ]);

        $provider = AIProvider::create([
            'provider_key' => 'openai',
            'name' => 'OpenAI',
            'api_key' => 'key',
            'default_model' => 'gpt-4',
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
            'status' => 'queued',
        ]);

        $job = new ProcessPipelineJob($run->id);

        $mockGenerationService = $this->createMock(\App\Modules\ContentGeneration\Services\ContentGenerationService::class);
        $mockGenerationService->method('generateContentForRun')
            ->willThrowException(new \Exception('Generation failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Generation failed');

        try {
            $job->handle($mockGenerationService);
        } finally {
            $run->refresh();
            $pipeline->refresh();
            $this->assertEquals('failed', $run->status);
            $this->assertEquals('failed', $pipeline->status);
            $this->assertEquals('Generation failed', $run->error_message);
        }
    }

    public function test_sync_site_data_job_failed_callback_resets_status()
    {
        $customer = Customer::create([
            'company_name' => 'Test Company',
            'owner_name' => 'Owner',
            'email' => 'owner@test.com',
            'status' => 'active',
        ]);

        $site = Site::create([
            'customer_id' => $customer->id,
            'domain_url' => 'https://example.com',
            'is_active' => true,
            'last_sync_status' => 'syncing',
        ]);

        $job = new SyncSiteDataJob($site);
        $job->failed(new \Exception('Sync failed'));

        $site->refresh();
        $this->assertEquals('failed', $site->last_sync_status);
    }

    public function test_publish_post_job_releases_on_retry_and_does_not_rethrow()
    {
        $customer = Customer::create([
            'company_name' => 'Test Company',
            'owner_name' => 'Owner',
            'email' => 'owner@test.com',
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
        ]);

        $site = Site::create([
            'customer_id' => $customer->id,
            'domain_url' => 'https://example.com',
            'is_active' => true,
        ]);

        $topic = Topic::create([
            'name' => 'Test Topic',
            'status' => 'active',
        ]);

        $content = GeneratedContent::create([
            'site_id' => $site->id,
            'topic_id' => $topic->id,
            'title' => 'Test Article',
            'content' => 'Test Content',
            'status' => 'approved',
        ]);

        $log = PublishingLog::create([
            'generated_content_id' => $content->id,
            'site_id' => $site->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        // Mock job attempts and release
        $job = $this->getMockBuilder(PublishPostJob::class)
            ->setConstructorArgs([$log->id])
            ->onlyMethods(['attempts', 'release'])
            ->getMock();

        $job->expects($this->once())
            ->method('attempts')
            ->willReturn(1); // attempt 1 < tries 3

        $job->expects($this->once())
            ->method('release');

        $mockPublishingService = $this->createMock(\App\Modules\Publishing\Services\PublishingService::class);
        $mockPublishingService->method('executePublish')
            ->willThrowException(new \Exception('Publish failed'));

        // Handle should not throw exception because attempts < tries, it should just call release and return early
        $job->handle($mockPublishingService);

        $log->refresh();
        $this->assertEquals('retrying', $log->status);
        $this->assertStringContainsString('Attempt 1 failed', $log->error_message);
    }
}
