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
        $this->fakeDriver = new class implements AIProviderClientInterface
        {
            public string $responseText = '[]';

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
                return [
                    'text' => $this->responseText,
                    'prompt_tokens' => 100,
                    'completion_tokens' => 500,
                    'total_tokens' => 600,
                    'estimated_cost' => 0.01,
                    'raw_response' => [],
                ];
            }
        };

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
        Queue::fake();

        $run = app(PipelineService::class)->triggerDiscovery($this->pipeline);

        $this->assertEquals(PipelineRun::TYPE_DISCOVERY, $run->run_type);
        $this->assertEquals('queued', $run->status);
        Queue::assertPushed(GenerateNewsCandidatesJob::class);
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
        $this->assertDatabaseHas('ai_request_logs', ['status' => 'success', 'total_tokens' => 600]);
    }

    public function test_discovery_fails_explicitly_when_unique_candidates_fall_short(): void
    {
        // Only 3 items; the retry attempt returns the same 3 (all duplicates).
        $this->fakeDriver->responseText = json_encode(array_slice($this->distinctCandidatesPayload(), 0, 3));

        $run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'queued',
            'run_type' => PipelineRun::TYPE_DISCOVERY,
        ]);

        try {
            (new GenerateNewsCandidatesJob($run->id))->handle(app(NewsDiscoveryService::class));
            $this->fail('Expected discovery shortfall to throw.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('unique candidates', $e->getMessage());
        }

        $run->refresh();
        $this->assertEquals('failed', $run->status);
        $this->assertNotNull($run->error_message);
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
