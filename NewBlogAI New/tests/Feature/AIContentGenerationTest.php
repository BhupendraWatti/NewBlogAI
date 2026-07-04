<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\ContentGeneration\Models\ContentRevision;
use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\ContentGeneration\Services\ContentGenerationService;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\SiteManager\Models\Site;
use App\Modules\TopicManager\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AIContentGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Site $site;

    protected Topic $topic;

    protected Prompt $prompt;

    protected AIProvider $provider;

    protected ContentPipeline $pipeline;

    protected PipelineRun $run;

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
            'domain_url' => 'https://generationwp.com',
            'api_key' => 'token',
            'is_active' => true,
        ]);

        $this->topic = Topic::create([
            'name' => 'Venture Capital in India',
            'category' => 'Finance',
            'status' => 'active',
            'generation_frequency' => 'weekly',
        ]);

        $this->prompt = Prompt::create([
            'name' => 'Finance Report Prompt',
            'promt' => 'Write about {{topic}} for {{website}} in {{language}}.',
            'category' => 'Finance',
            'status' => 'active',
        ]);

        $this->provider = AIProvider::create([
            'provider_key' => 'openai',
            'name' => 'OpenAI',
            'api_key' => 'valid-api-key',
            'default_model' => 'gpt-4o',
            'is_enabled' => true,
        ]);

        $this->pipeline = ContentPipeline::create([
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'prompt_id' => $this->prompt->id,
            'ai_provider_id' => $this->provider->id,
            'language' => 'hi',
            'generation_type' => 'article',
            'is_active' => true,
        ]);

        $this->run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'queued',
        ]);
    }

    public function test_ai_generation_service_orchestrates_successful_generation(): void
    {
        // Fake OpenAI completions API
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'भारतीय उद्यम पूंजी बाजार में अभूतपूर्व वृद्धि देखी जा रही है।',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 120,
                    'completion_tokens' => 150,
                    'total_tokens' => 270,
                ],
            ], 200),
        ]);

        $service = resolve(ContentGenerationService::class);
        $article = $service->generateContentForRun($this->run);

        // Verify generated content table
        $this->assertDatabaseHas('generated_contents', [
            'id' => $article->id,
            'pipeline_id' => $this->pipeline->id,
            'title' => 'Article: Venture Capital in India - '.now()->format('Y-m-d'),
            'status' => 'draft',
        ]);

        // Verify content compiles variables
        $this->assertEquals('भारतीय उद्यम पूंजी बाजार में अभूतपूर्व वृद्धि देखी जा रही है।', $article->content);

        // Verify initial revision entry
        $this->assertDatabaseHas('content_revisions', [
            'generated_content_id' => $article->id,
            'title' => $article->title,
        ]);

        // Verify AI request logging history with token usage and pricing
        $this->assertDatabaseHas('ai_request_logs', [
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'prompt_tokens' => 120,
            'completion_tokens' => 150,
            'total_tokens' => 270,
            'status' => 'success',
        ]);

        // Verify pipeline runs transitions to completed
        $this->assertEquals('completed', $this->run->fresh()->status);
        $this->assertEquals('completed', $this->pipeline->fresh()->status);
    }

    public function test_user_can_edit_generated_content_creating_revisions(): void
    {
        $article = GeneratedContent::create([
            'pipeline_id' => $this->pipeline->id,
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'title' => 'Original Title',
            'content' => 'Original Content',
            'status' => 'draft',
        ]);

        // Create initial revision
        ContentRevision::create([
            'generated_content_id' => $article->id,
            'title' => 'Original Title',
            'content' => 'Original Content',
        ]);

        // Edit via API
        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/articles/{$article->id}", [
                'title' => 'Updated Title',
                'content' => 'Updated Content',
            ]);

        $response->assertStatus(200);

        // Assert content is modified
        $this->assertDatabaseHas('generated_contents', [
            'id' => $article->id,
            'title' => 'Updated Title',
        ]);

        // Assert revision is logged
        $this->assertDatabaseHas('content_revisions', [
            'generated_content_id' => $article->id,
            'title' => 'Updated Title',
            'content' => 'Updated Content',
        ]);

        // Assert there are 2 revisions total
        $this->assertEquals(2, ContentRevision::where('generated_content_id', $article->id)->count());
    }

    public function test_admin_can_update_approval_status(): void
    {
        $article = GeneratedContent::create([
            'pipeline_id' => $this->pipeline->id,
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'title' => 'Revision Title',
            'content' => 'Revision Content',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/articles/{$article->id}/status", [
                'status' => 'approved',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('approved', $article->fresh()->status);
    }
}
