<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\ContentPipeline\Jobs\ProcessPipelineJob;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\SiteManager\Models\Site;
use App\Modules\TopicManager\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ContentPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Site $site;

    protected Topic $topic;

    protected Prompt $prompt;

    protected AIProvider $provider;

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
            'domain_url' => 'https://activewp.com',
            'api_key' => 'token',
            'is_active' => true,
        ]);

        $this->topic = Topic::create([
            'name' => 'Generative AI',
            'category' => 'Tech',
            'status' => 'active',
            'generation_frequency' => 'daily',
        ]);

        $this->prompt = Prompt::create([
            'name' => 'Standard Prompt',
            'prompt' => 'Write content.',
            'category' => 'Tech',
            'status' => 'active',
        ]);

        $this->provider = AIProvider::create([
            'provider_key' => 'gemini',
            'name' => 'Google Gemini',
            'api_key' => 'some-encrypted-key',
            'default_model' => 'gemini-2.5-flash',
            'is_enabled' => true,
        ]);
    }

    public function test_admin_can_create_pipeline(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/pipelines', [
                'site_id' => $this->site->id,
                'topic_id' => $this->topic->id,
                'prompt_id' => $this->prompt->id,
                'ai_provider_id' => $this->provider->id,
                'language' => 'en',
                'generation_type' => 'article',
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('content_pipelines', [
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'prompt_id' => $this->prompt->id,
            'ai_provider_id' => $this->provider->id,
        ]);
    }

    public function test_cannot_create_pipeline_with_inactive_site(): void
    {
        $this->site->update(['is_active' => false]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/pipelines', [
                'site_id' => $this->site->id,
                'topic_id' => $this->topic->id,
                'prompt_id' => $this->prompt->id,
                'ai_provider_id' => $this->provider->id,
                'language' => 'en',
                'generation_type' => 'article',
            ]);

        $response->assertStatus(500); // throws validation exception due to inactive site
    }

    public function test_cannot_create_pipeline_with_disabled_ai_provider(): void
    {
        $this->provider->update(['is_enabled' => false]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/pipelines', [
                'site_id' => $this->site->id,
                'topic_id' => $this->topic->id,
                'prompt_id' => $this->prompt->id,
                'ai_provider_id' => $this->provider->id,
                'language' => 'en',
                'generation_type' => 'article',
            ]);

        $response->assertStatus(500); // throws validation exception due to disabled AI provider
    }

    public function test_trigger_pipeline_run_dispatching(): void
    {
        Queue::fake();

        $pipeline = ContentPipeline::create([
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'prompt_id' => $this->prompt->id,
            'ai_provider_id' => $this->provider->id,
            'language' => 'en',
            'generation_type' => 'article',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/pipelines/{$pipeline->id}/execute");

        $response->assertStatus(202)
            ->assertJsonPath('run.status', 'queued');

        $this->assertDatabaseHas('pipeline_runs', [
            'pipeline_id' => $pipeline->id,
            'status' => 'queued',
        ]);

        Queue::assertPushed(ProcessPipelineJob::class);
    }
}
