<?php

namespace Tests\Feature;

use App\Modules\AIProviderManager\Drivers\GroqDriver;
use App\Modules\ContentPipeline\Jobs\ProcessPipelineJob;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\ContentPipeline\Services\PipelineService;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\SiteManager\Models\Site;
use App\Modules\AIProviderManager\Models\AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PipelineOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected Site $site;
    protected Prompt $prompt;
    protected AIProvider $provider;
    protected ContentPipeline $pipeline;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::create([
            'name' => 'Optimization Test Site',
            'domain_url' => 'https://optimizationtest.com',
            'api_key' => 'token',
            'is_active' => true,
        ]);

        $this->prompt = Prompt::create([
            'name' => 'Default Prompt',
            'prompt' => 'Write about {{category}}',
            'category' => 'General',
            'status' => 'active',
        ]);

        $this->provider = AIProvider::create([
            'provider_key' => 'groq',
            'name' => 'Groq Test',
            'api_key' => 'groq-test-key',
            'default_model' => 'llama-3.3-70b-versatile',
            'is_enabled' => true,
        ]);

        $this->pipeline = ContentPipeline::create([
            'site_id' => $this->site->id,
            'news_category' => 'global',
            'prompt_id' => $this->prompt->id,
            'ai_provider_id' => $this->provider->id,
            'language' => 'en',
            'is_active' => true,
        ]);
    }

    /**
     * Test that PipelineService prevents starting duplicate runs for the same pipeline when one is active.
     */
    public function test_trigger_run_prevents_duplicate_active_runs(): void
    {
        Queue::fake();

        $service = resolve(PipelineService::class);

        // Start first run -> sets pipeline status to queued
        $run1 = $service->triggerRun($this->pipeline);
        $this->assertEquals('queued', $run1->status);

        // Attempting to trigger second run while status is queued/processing should throw exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A generation run is already in progress for this pipeline.');

        $service->triggerRun($this->pipeline);
    }

    /**
     * Test that ProcessPipelineJob enforces atomic lock to prevent concurrent processing of the same run.
     */
    public function test_process_pipeline_job_prevents_concurrent_execution(): void
    {
        $run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'queued',
        ]);

        // Manually acquire the lock first
        $lockKey = "pipeline_run_processing_{$run->id}";
        $lock = Cache::lock($lockKey, 300);
        $lock->get();

        // Spy on logger to verify warnings
        Log::shouldReceive('warning')
            ->once()
            ->with("ProcessPipelineJob ID {$run->id} is already being processed. Skipping execution.");
        Log::shouldReceive('error')->never(); // Should not throw error or fail

        $job = new ProcessPipelineJob($run->id);
        $job->handle(resolve(\App\Modules\ContentGeneration\Services\ContentGenerationService::class));

        $lock->release();
    }

    /**
     * Test that GroqDriver handles 429 errors with exponential backoff and succeeds on a subsequent attempt.
     */
    public function test_groq_driver_retries_on_429_and_succeeds(): void
    {
        // Mock 2 rate-limited responses followed by 1 successful response
        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::sequence()
                ->push(['error' => 'Rate limit exceeded'], 429)
                ->push(['error' => 'Rate limit exceeded'], 429)
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => 'Successful after retries',
                            ],
                        ],
                    ],
                    'usage' => [
                        'prompt_tokens' => 50,
                        'completion_tokens' => 80,
                        'total_tokens' => 130,
                    ],
                ], 200),
        ]);

        $driver = new GroqDriver();
        
        // Measure execution time to confirm sleep backoff happened (2s + 4s = 6s minimum sleep)
        $start = microtime(true);
        $result = $driver->generate('test-api-key', 'Test Prompt', 'llama-3.3-70b-versatile', [
            'task' => 'unit_test'
        ]);
        $duration = microtime(true) - $start;

        $this->assertEquals('Successful after retries', $result['text']);
        $this->assertEquals(50, $result['prompt_tokens']);
        $this->assertEquals(80, $result['completion_tokens']);
        
        // Assert that backoff slept for at least 6 seconds (2^1 = 2s, 2^2 = 4s)
        $this->assertGreaterThanOrEqual(6.0, $duration);
    }
}
