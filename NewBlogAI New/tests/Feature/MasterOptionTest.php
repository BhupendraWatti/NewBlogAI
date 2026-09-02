<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\SiteManager\Models\Site;
use App\Modules\SystemSettings\Models\MasterOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterOptionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Newsroom Editor',
            'email' => 'editor@example.com',
            'password' => bcrypt('secret123'),
        ]);
        $this->user->role = 1; // Super Admin
        $this->user->save();
    }

    public function test_user_can_retrieve_grouped_master_options(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/master-options/grouped');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'topics',
                    'countries',
                    'states',
                ],
            ]);

        // Default seeds should include India and Indian Startups
        $topics = collect($response->json('data.topics'));
        $countries = collect($response->json('data.countries'));
        $states = collect($response->json('data.states'));

        $this->assertTrue($topics->contains('name', 'Indian Startups'));
        $this->assertTrue($countries->contains('name', 'India'));
        $this->assertTrue($states->contains('name', 'Maharashtra'));
    }

    public function test_user_can_list_and_filter_master_options(): void
    {
        // Filter by type=country
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/master-options?type=country&all=1');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertEquals('country', $item['type']);
        }

        // Search by name
        $searchRes = $this->actingAs($this->user)
            ->getJson('/api/v1/master-options?search=Maharashtra');

        $searchRes->assertStatus(200);
        $searchData = $searchRes->json('data') ?? $searchRes->json();
        $this->assertCount(1, $searchData);
        $this->assertEquals('Maharashtra', $searchData[0]['name']);
    }

    public function test_user_can_create_topic_country_and_state(): void
    {
        // 1. Create a Country
        $countryRes = $this->actingAs($this->user)
            ->postJson('/api/v1/master-options', [
                'type' => 'country',
                'name' => 'Japan',
                'code' => 'JP',
                'sort_order' => 10,
                'is_active' => true,
            ]);

        $countryRes->assertStatus(201)
            ->assertJsonPath('data.name', 'Japan')
            ->assertJsonPath('data.code', 'JP');

        $countryId = $countryRes->json('data.id');

        // 2. Create a State linked to Japan
        $stateRes = $this->actingAs($this->user)
            ->postJson('/api/v1/master-options', [
                'type' => 'state',
                'name' => 'Tokyo',
                'code' => '13',
                'parent_id' => $countryId,
                'sort_order' => 1,
                'is_active' => true,
            ]);

        $stateRes->assertStatus(201)
            ->assertJsonPath('data.name', 'Tokyo')
            ->assertJsonPath('data.parent_id', $countryId);

        // 3. Create a Topic
        $topicRes = $this->actingAs($this->user)
            ->postJson('/api/v1/master-options', [
                'type' => 'topic',
                'name' => 'Robotics & Automation',
                'code' => 'robotics',
                'sort_order' => 5,
                'is_active' => true,
            ]);

        $topicRes->assertStatus(201)
            ->assertJsonPath('data.name', 'Robotics & Automation');

        $this->assertDatabaseHas('master_options', ['name' => 'Japan']);
        $this->assertDatabaseHas('master_options', ['name' => 'Tokyo', 'parent_id' => $countryId]);
        $this->assertDatabaseHas('master_options', ['name' => 'Robotics & Automation']);
    }

    public function test_user_can_update_master_option(): void
    {
        $option = MasterOption::ofType('topic')->first();
        $this->assertNotNull($option);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/master-options/{$option->id}", [
                'name' => 'Global Technology & AI',
                'is_active' => false,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Global Technology & AI')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('master_options', [
            'id' => $option->id,
            'name' => 'Global Technology & AI',
            'is_active' => false,
        ]);
    }

    public function test_user_can_delete_master_option(): void
    {
        $option = MasterOption::create([
            'type' => 'topic',
            'name' => 'Temporary Topic To Delete',
            'code' => 'temp-delete',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/master-options/{$option->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Master option deleted successfully.');

        $this->assertDatabaseMissing('master_options', [
            'id' => $option->id,
        ]);
    }

    public function test_pipeline_can_be_created_with_target_country_and_state(): void
    {
        $site = Site::create([
            'name' => 'Daily Tech News',
            'domain_url' => 'https://dailytech.test',
            'api_key' => 'test_key',
            'status' => 'active',
            'is_active' => true,
        ]);

        $provider = AIProvider::create([
            'name' => 'Gemini Pro',
            'provider_key' => 'gemini',
            'api_key' => 'fake_api_key',
            'is_enabled' => true,
            'is_active' => true,
        ]);

        $prompt = Prompt::create([
            'name' => 'Universal News',
            'prompt' => 'Write news about {{category}}',
            'status' => 'active',
        ]);

        $payload = [
            'site_id' => $site->id,
            'news_category' => 'Indian Startups',
            'target_country' => 'India',
            'target_state' => 'Karnataka',
            'prompt_id' => $prompt->id,
            'ai_provider_id' => $provider->id,
            'language' => 'en',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/pipelines', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.news_category', 'Indian Startups')
            ->assertJsonPath('data.target_country', 'India')
            ->assertJsonPath('data.target_state', 'Karnataka');

        $this->assertDatabaseHas('content_pipelines', [
            'news_category' => 'Indian Startups',
            'target_country' => 'India',
            'target_state' => 'Karnataka',
        ]);
    }

    public function test_prompt_engine_and_topic_resolver_support_dynamic_location_parameters(): void
    {
        $site = Site::create([
            'name' => 'USA Tech News',
            'domain_url' => 'https://usatech.test',
            'api_key' => 'test_key',
            'status' => 'active',
            'is_active' => true,
        ]);

        $provider = AIProvider::create([
            'name' => 'OpenAI GPT',
            'provider_key' => 'openai',
            'api_key' => 'fake_key',
            'is_enabled' => true,
            'is_active' => true,
        ]);

        $prompt = Prompt::create([
            'name' => 'Universal Template',
            'prompt' => 'Write news for {{website}} in {{country}} state {{state}} at {{location}}',
            'status' => 'active',
        ]);

        $pipeline = ContentPipeline::create([
            'site_id' => $site->id,
            'news_category' => 'Technology',
            'target_country' => 'India',
            'target_state' => 'Karnataka',
            'prompt_id' => $prompt->id,
            'ai_provider_id' => $provider->id,
            'language' => 'en',
            'is_active' => true,
        ]);

        $run = \App\Modules\ContentPipeline\Models\PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'pending',
        ]);

        // Test TopicResolverService derives dynamic location metadata
        $context = new \App\Modules\ContentPipeline\DTOs\PipelineContext($run, $pipeline);
        $resolver = new \App\Modules\TopicManager\Services\TopicResolverService();
        $resolvedContext = $resolver->handle($context);

        $this->assertEquals('India', $resolvedContext->metadata['target_country']);
        $this->assertEquals('Karnataka', $resolvedContext->metadata['target_state']);
        $this->assertEquals('Karnataka, India', $resolvedContext->metadata['target_location']);

        // Test PromptEngine compiles dynamic location variables and instructions
        $promptEngine = new \App\Modules\ContentPipeline\Services\PromptEngine();
        $variables = [
            'website' => 'usatech.test',
            'country' => $pipeline->target_country,
            'state' => $pipeline->target_state,
            'location' => 'Karnataka, India',
        ];

        $compiled = $promptEngine->buildFullPrompt($resolvedContext, $prompt->prompt, $variables);

        $this->assertStringContainsString('in India state Karnataka at Karnataka, India', $compiled);
        $this->assertStringContainsString('Target Region: This article is targeted for Karnataka, India', $compiled);
    }
}
