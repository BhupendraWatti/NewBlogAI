<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\AIProviderManager\Contracts\AIProviderClientInterface;
use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\AIProviderManager\Services\AIProviderService;
use App\Modules\ContentGeneration\Models\AIRequestLog;
use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\ContentPipeline\Jobs\GenerateNewsCandidatesJob;
use App\Modules\ContentPipeline\Jobs\ProcessPipelineJob;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\ContentPipeline\Models\NewsCandidate;
use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\ContentPipeline\Services\CandidateSelectionService;
use App\Modules\ContentPipeline\Services\DuplicateDetectionService;
use App\Modules\ContentPipeline\Services\NewsDiscoveryService;
use App\Modules\ContentPipeline\Services\PipelineService;
use App\Modules\CustomerManager\Models\Customer;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\SiteManager\Models\Site;
use App\Modules\SubscriptionManager\Services\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

final class CoverageNewsroomFakeDriver implements AIProviderClientInterface
{
    public string $responseText = '[]';

    /** @var array<int, string> */
    public array $responseQueue = [];

    public int $calls = 0;

    /** @var array<int, array> */
    public array $optionsHistory = [];

    /** @var array<int, string> */
    public array $promptsHistory = [];

    public function testConnection(string $apiKey, ?string $model = null): bool
    {
        return true;
    }

    public function getConfig(): array
    {
        return [];
    }

    public function generate(string $apiKey, string $prompt, ?string $model = null, array $options = []): array
    {
        $this->calls++;
        $this->optionsHistory[] = $options;
        $this->promptsHistory[] = $prompt;
        $text = $this->responseQueue !== []
            ? array_shift($this->responseQueue)
            : $this->responseText;

        return [
            'text' => $text,
            'prompt_tokens' => 100,
            'completion_tokens' => 500,
            'total_tokens' => 600,
            'estimated_cost' => 0.01,
            'raw_response' => [],
        ];
    }
}

class CoverageNewsroomWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Site $site;

    protected ContentPipeline $pipeline;

    protected User $employee;

    protected object $fakeDriver;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = Customer::create([
            'company_name' => 'Acme Corp',
            'owner_name' => 'Alice Owner',
            'email' => 'alice@acme.com',
            'status' => 'active',
        ]);

        $this->site = Site::create([
            'customer_id' => $customer->id,
            'domain_url' => 'https://acmenews.com',
            'name' => 'Acme News',
            'api_key' => 'test-key',
            'is_active' => true,
            'status' => 'connected',
            'timezone' => 'UTC',
        ]);

        $prompt = Prompt::create([
            'name' => 'News Template',
            'prompt' => 'Write a news article about {{headline}}. Context: {{summary}}',
            'category' => 'News',
            'status' => 'active',
        ]);

        $provider = AIProvider::create([
            'provider_key' => 'openai',
            'name' => 'OpenAI',
            'api_key' => 'test-key',
            'default_model' => 'gpt-4o',
            'is_enabled' => true,
        ]);

        $this->pipeline = ContentPipeline::create([
            'site_id' => $this->site->id,
            'news_category' => 'technology',
            'prompt_id' => $prompt->id,
            'ai_provider_id' => $provider->id,
            'language' => 'en',
            'generation_type' => 'article',
            'is_active' => true,
        ]);

        $this->employee = User::create([
            'name' => 'Eddie Employee',
            'email' => 'eddie@acme.com',
            'password' => bcrypt('password'),
        ]);

        // Entitlement boundary is mocked: quota logic has its own test suite.
        $entitlements = Mockery::mock(EntitlementService::class);
        $entitlements->shouldReceive('assertCanGenerate')->andReturnNull();
        $entitlements->shouldReceive('assertProviderAvailable')->andReturnNull();
        $entitlements->shouldReceive('assertAnyProviderAvailable')->andReturnNull();
        $entitlements->shouldReceive('reserveGeneration')->andReturnUsing(function () {
            return AIRequestLog::create([
                'provider' => 'openai',
                'model' => 'gpt-4o',
                'site_id' => $this->site->id,
                'status' => 'reserved',
                'execution_time_ms' => 0,
            ]);
        });
        $this->app->instance(EntitlementService::class, $entitlements);

        // AI provider boundary is mocked with a scriptable fake driver.
        $this->fakeDriver = new CoverageNewsroomFakeDriver;

        $providerService = Mockery::mock(AIProviderService::class);
        $providerService->shouldReceive('getDriver')->andReturn($this->fakeDriver);
        $this->app->instance(AIProviderService::class, $providerService);
    }

    /**
     * Twelve clearly distinct technology news candidates.
     */
    protected function distinctCandidatesPayload(): array
    {
        $events = [
            ['Quantum chip maker unveils 1000-qubit processor', ['quantum', 'processor', 'computing']],
            ['EU passes landmark AI liability directive', ['eu', 'regulation', 'liability']],
            ['Major cloud outage disrupts banking apps in Asia', ['cloud', 'outage', 'banking']],
            ['Startup demonstrates solid-state EV battery range record', ['battery', 'ev', 'solid-state']],
            ['Open source foundation forks popular database project', ['open-source', 'database', 'fork']],
            ['Smartphone giant recalls flagship over overheating', ['smartphone', 'recall', 'overheating']],
            ['Satellite internet constellation reaches global coverage', ['satellite', 'internet', 'constellation']],
            ['Researchers crack post-quantum encryption candidate', ['encryption', 'research', 'security']],
            ['Chipmaker announces 2nm fabrication breakthrough', ['chip', 'fabrication', 'semiconductor']],
            ['Social platform rolls out decentralized identity system', ['social', 'identity', 'decentralized']],
            ['Robotics firm deploys warehouse humanoids at scale', ['robotics', 'warehouse', 'humanoid']],
            ['Gaming engine adds real-time neural rendering', ['gaming', 'rendering', 'neural']],
        ];

        return array_map(fn (array $event, int $i) => [
            'title' => $event[0],
            'summary' => "Summary of event {$i}: ".$event[0].'. Additional factual details reported today.',
            'source_references' => [['name' => 'Reuters', 'url' => 'https://reuters.com/item-'.$i]],
            'keywords' => $event[1],
            'trend_score' => 60 + $i,
            'freshness_score' => 90,
            'event_date' => now()->toDateString(),
        ], $events, array_keys($events));
    }

    public function test_trigger_discovery_creates_discovery_run_and_queues_job(): void
    {
        $run = app(PipelineService::class)->triggerDiscovery($this->pipeline);

        $this->assertEquals(PipelineRun::TYPE_DISCOVERY, $run->run_type);
        $this->assertEquals('queued', $run->status);
        $this->assertEquals(0, $run->candidates()->count());
    }

    public function test_unkeyed_requested_provider_does_not_block_discovery_queueing(): void
    {
        AIProvider::create([
            'provider_key' => 'groq',
            'name' => 'Groq without credentials',
            'api_key' => null,
            'default_model' => 'llama-3.3-70b-versatile',
            'is_enabled' => true,
        ]);

        $run = app(PipelineService::class)->triggerDiscovery($this->pipeline, 'groq');

        $this->assertSame('queued', $run->status);
        $this->assertSame('groq', $run->properties['requested_discovery_provider_key']);
        $this->assertArrayNotHasKey('discovery_provider_id', $run->properties);
    }

    public function test_grounded_provider_is_preferred_without_hiding_keyed_fallbacks(): void
    {
        $gemini = AIProvider::create([
            'provider_key' => 'gemini',
            'name' => 'Gemini',
            'api_key' => 'gemini-key',
            'default_model' => 'gemini-2.5-flash',
            'is_enabled' => true,
            'priority' => 1,
        ]);

        $providers = app(NewsDiscoveryService::class)->getAvailableProviders($gemini);

        $this->assertSame('gemini', $providers->first()->provider_key);
        $this->assertTrue($providers->contains(fn (AIProvider $provider) => $provider->provider_key === 'openai'));
    }

    public function test_discovery_terminalizes_clearly_when_no_provider_has_credentials(): void
    {
        AIProvider::query()->update(['api_key' => null]);
        $run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'queued',
            'run_type' => PipelineRun::TYPE_DISCOVERY,
            'properties' => [
                'telemetry' => [
                    'stage' => 'queued',
                    'timeout_ms' => NewsDiscoveryService::REQUEST_TIMEOUT_SECONDS * 1000,
                ],
            ],
        ]);

        try {
            app(NewsDiscoveryService::class)->discover($run);
            $this->fail('Discovery should fail when every provider is unkeyed.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('at least one API key', $e->getMessage());
        }

        $run->refresh();
        $this->assertSame('failed', $run->status);
        $this->assertSame('failed', $run->properties['telemetry']['stage']);
        $this->assertNotNull($run->completed_at);
    }

    /**
     * Verify the discovery token budget is bounded.
     *
     * DISCOVERY_MAX_TOKENS was raised from 4096 → 8192 (Phase 11, 2026-08-07)
     * because grounded search responses with rich source URLs were exceeding
     * 4096 output tokens, causing mid-array JSON truncation and the misleading
     * 'No error' parse failure. 8192 is still within Gemini 2.5 Flash's output
     * budget and prevents free-tier quota exhaustion per minute.
     *
     * DISCOVERY_BATCH_SIZE remains ≤ 4 to keep each grounded call small.
     */
    public function test_discovery_uses_safe_gemini_token_budget(): void
    {
        $this->assertLessThanOrEqual(8192, NewsDiscoveryService::DISCOVERY_MAX_TOKENS);
        $this->assertGreaterThan(4096, NewsDiscoveryService::DISCOVERY_MAX_TOKENS,
            'DISCOVERY_MAX_TOKENS should be > 4096 to prevent JSON truncation on grounded responses.'
        );
        $this->assertLessThanOrEqual(4, NewsDiscoveryService::DISCOVERY_BATCH_SIZE);
    }

    public function test_discovery_disables_gemini_dynamic_thinking_to_protect_json_output_budget(): void
    {
        AIProvider::query()->update([
            'provider_key' => 'gemini',
            'name' => 'Gemini',
            'default_model' => 'gemini-2.5-flash',
        ]);
        $this->fakeDriver->responseText = json_encode(array_slice($this->distinctCandidatesPayload(), 0, 4));

        $run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'queued',
            'run_type' => PipelineRun::TYPE_DISCOVERY,
        ]);

        (new GenerateNewsCandidatesJob($run->id))->handle(app(NewsDiscoveryService::class));

        $this->assertNotEmpty($this->fakeDriver->optionsHistory);
        $this->assertSame(0, $this->fakeDriver->optionsHistory[0]['thinking_budget']);
        $this->assertSame(0, $this->fakeDriver->optionsHistory[0]['max_retries']);
        $this->assertLessThanOrEqual(NewsDiscoveryService::REQUEST_TIMEOUT_SECONDS, $this->fakeDriver->optionsHistory[0]['timeout']);
        $this->assertStringContainsString('2 to 4 concise factual sentences', $this->fakeDriver->promptsHistory[0]);
        $this->assertStringContainsString('two independent credible publisher sources when available', $this->fakeDriver->promptsHistory[0]);
    }

    public function test_discovery_retries_when_gemini_returns_early_truncated_json(): void
    {
        $this->fakeDriver->responseQueue = [
            '[',
            json_encode(array_slice($this->distinctCandidatesPayload(), 0, 4)),
        ];

        $run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'queued',
            'run_type' => PipelineRun::TYPE_DISCOVERY,
        ]);

        (new GenerateNewsCandidatesJob($run->id))->handle(app(NewsDiscoveryService::class));

        $run->refresh();
        $this->assertEquals(PipelineRun::STATUS_READY, $run->status);
        $this->assertGreaterThanOrEqual(1, $run->candidates()->count());
        $this->assertGreaterThanOrEqual(2, $this->fakeDriver->calls);
    }

    public function test_discovery_persists_exactly_nine_unique_candidates(): void
    {
        $payload = $this->distinctCandidatesPayload();
        // Inject two near-duplicates of item 1 — must be filtered out.
        $payload[10] = array_merge($payload[0], ['title' => 'Quantum chip maker unveils 1000 qubit processor!']);
        $payload[11] = array_merge($payload[1], ['title' => 'EU passes landmark AI liability directive today']);
        $this->fakeDriver->responseText = json_encode($payload);

        $run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'queued',
            'run_type' => PipelineRun::TYPE_DISCOVERY,
        ]);

        (new GenerateNewsCandidatesJob($run->id))->handle(app(NewsDiscoveryService::class));

        $run->refresh();
        $this->assertEquals(PipelineRun::STATUS_READY, $run->status);

        $candidates = $run->candidates;
        $this->assertCount(9, $candidates);
        $this->assertEquals(range(1, 9), $candidates->pluck('position')->all());
        $this->assertEquals(9, $candidates->pluck('uniqueness_hash')->unique()->count());
        $this->assertTrue($candidates->every(fn ($c) => $c->status === NewsCandidate::STATUS_CANDIDATE));

        // Usage tracking: reservation updated to success with aggregated tokens.
        $this->assertDatabaseHas('ai_request_logs', ['status' => 'success', 'total_tokens' => 1200]);

        $telemetry = $run->properties['telemetry'];
        $this->assertSame('completed', $telemetry['stage']);
        $this->assertSame(2, $telemetry['requests_completed']);
        $this->assertSame(1200, $telemetry['tokens']['total']);
        $this->assertEqualsWithDelta(0.02, $telemetry['estimated_cost_usd'], 0.000001);
        $this->assertSame(NewsDiscoveryService::REQUEST_TIMEOUT_SECONDS * 1000, $telemetry['timeout_ms']);
    }

    public function test_city_focused_discovery_does_not_reject_its_own_city_stories(): void
    {
        $payload = array_map(
            fn (array $candidate) => array_merge($candidate, [
                'geo_city' => 'Ujjain',
                'geo_state' => 'Madhya Pradesh',
            ]),
            array_slice($this->distinctCandidatesPayload(), 0, 9),
        );
        $this->fakeDriver->responseText = json_encode($payload);
        $this->pipeline->update(['news_category' => 'Latest Ujjain news']);

        $run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'queued',
            'run_type' => PipelineRun::TYPE_DISCOVERY,
        ]);

        (new GenerateNewsCandidatesJob($run->id))->handle(app(NewsDiscoveryService::class));

        $run->refresh();
        $this->assertSame(PipelineRun::STATUS_READY, $run->status);
        $this->assertCount(9, $run->candidates);
        $this->assertStringContainsString('multiple stories MAY share that location', $this->fakeDriver->promptsHistory[0]);
        $this->assertStringNotContainsString('Each story MUST come from a DIFFERENT city', $this->fakeDriver->promptsHistory[0]);
    }

    public function test_queued_discovery_failure_is_contained_at_the_job_boundary(): void
    {
        $run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'processing',
            'run_type' => PipelineRun::TYPE_DISCOVERY,
        ]);
        $service = Mockery::mock(NewsDiscoveryService::class);
        $service->shouldReceive('discover')
            ->once()
            ->andThrow(new \RuntimeException('provider output failed'));

        (new GenerateNewsCandidatesJob($run->id))->handle($service);

        $run->refresh();
        $this->assertSame('failed', $run->status);
        $this->assertSame('provider output failed', $run->error_message);
    }

    public function test_discovery_fails_explicitly_when_unique_candidates_fall_short(): void
    {
        // Only 0 items; the retry attempt returns the same 0 (all duplicates).
        $this->fakeDriver->responseText = json_encode(array_slice($this->distinctCandidatesPayload(), 0, 0));

        $run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'queued',
            'run_type' => PipelineRun::TYPE_DISCOVERY,
        ]);

        (new GenerateNewsCandidatesJob($run->id))->handle(app(NewsDiscoveryService::class));

        $run->refresh();
        $this->assertEquals('failed', $run->status);
        $this->assertStringContainsString('candidates', $run->error_message);
        $this->assertEquals(0, $run->candidates()->count());
    }

    public function test_discovery_rejects_candidates_outside_the_forty_eight_hour_window(): void
    {
        $payload = array_map(function (array $candidate): array {
            $candidate['event_date'] = now()->subDays(5)->toDateString();
            $candidate['published_at_relative'] = '5 days ago';

            return $candidate;
        }, $this->distinctCandidatesPayload());
        $this->fakeDriver->responseText = json_encode($payload);

        $run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'queued',
            'run_type' => PipelineRun::TYPE_DISCOVERY,
        ]);

        (new GenerateNewsCandidatesJob($run->id))->handle(app(NewsDiscoveryService::class));

        $run->refresh();
        $this->assertEquals('failed', $run->status);
        $this->assertStringContainsString('candidates', $run->error_message);
        $this->assertEquals(0, $run->candidates()->count());
    }

    protected function readyRunWithCandidates(): PipelineRun
    {
        $this->fakeDriver->responseText = json_encode($this->distinctCandidatesPayload());

        $run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'queued',
            'run_type' => PipelineRun::TYPE_DISCOVERY,
        ]);

        (new GenerateNewsCandidatesJob($run->id))->handle(app(NewsDiscoveryService::class));

        return $run->refresh();
    }

    public function test_selecting_a_candidate_triggers_full_generation_for_it_only(): void
    {
        $run = $this->readyRunWithCandidates();
        $candidate = $run->candidates->first();

        Queue::fake();

        $fullRun = app(CandidateSelectionService::class)->select($candidate, $this->employee->id);

        $candidate->refresh();
        $run->refresh();

        $this->assertEquals(NewsCandidate::STATUS_SELECTED, $candidate->status);
        $this->assertEquals($this->employee->id, $candidate->selected_by);
        $this->assertEquals($fullRun->id, $candidate->full_run_id);
        $this->assertEquals('completed', $run->status);

        $this->assertEquals(PipelineRun::TYPE_FULL, $fullRun->run_type);
        $this->assertEquals($candidate->title, $fullRun->properties['selected_candidate']['title']);

        Queue::assertPushed(ProcessPipelineJob::class);

        // The other 8 candidates were never generated into full articles.
        $this->assertEquals(8, $run->candidates()->where('status', NewsCandidate::STATUS_CANDIDATE)->count());
    }

    public function test_only_one_candidate_may_be_selected_per_coverage_run(): void
    {
        $run = $this->readyRunWithCandidates();
        Queue::fake();

        $selection = app(CandidateSelectionService::class);
        $selection->select($run->candidates->first(), $this->employee->id);

        $this->expectException(\InvalidArgumentException::class);
        $selection->select($run->refresh()->candidates()->where('status', NewsCandidate::STATUS_CANDIDATE)->first(), $this->employee->id);
    }

    public function test_selection_rejects_candidate_duplicating_published_news(): void
    {
        $run = $this->readyRunWithCandidates();
        $candidate = $run->candidates->first();

        // A near-identical article was published after discovery.
        GeneratedContent::create([
            'site_id' => $this->site->id,
            'pipeline_id' => $this->pipeline->id,
            'title' => $candidate->title,
            'content' => 'Already published body.',
            'status' => 'published',
        ]);

        Queue::fake();

        try {
            app(CandidateSelectionService::class)->select($candidate, $this->employee->id);
            $this->fail('Expected duplicate selection to be rejected.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('duplicates', $e->getMessage());
        }

        $this->assertEquals(NewsCandidate::STATUS_DUPLICATE, $candidate->fresh()->status);
        Queue::assertNotPushed(ProcessPipelineJob::class);
    }

    public function test_duplicate_detection_heuristics(): void
    {
        $service = app(DuplicateDetectionService::class);

        $this->assertGreaterThanOrEqual(
            DuplicateDetectionService::TITLE_SIMILARITY_THRESHOLD,
            $service->titleSimilarity('EU passes landmark AI liability directive', 'EU passes landmark AI liability directive today')
        );

        $this->assertLessThan(
            DuplicateDetectionService::TITLE_SIMILARITY_THRESHOLD,
            $service->titleSimilarity('Quantum chip maker unveils processor', 'Football club wins national championship')
        );

        $this->assertEquals(1.0, $service->keywordOverlap(['ai', 'eu'], ['AI', 'EU']));
        $this->assertEquals(0.0, $service->keywordOverlap(['ai'], ['football']));
    }
}
