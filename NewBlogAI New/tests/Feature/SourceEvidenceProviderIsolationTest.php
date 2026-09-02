<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\AIProviderManager\Services\AIProviderService;
use App\Modules\ContentGeneration\Services\ContentGenerationService;
use App\Modules\ContentPipeline\DTOs\PipelineContext;
use App\Modules\ContentPipeline\Exceptions\SourceEvidenceException;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\SubscriptionManager\Services\EntitlementService;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

final class SourceEvidenceProviderIsolationTest extends TestCase
{
    public function test_source_failure_does_not_fail_over_or_mutate_any_ai_provider(): void
    {
        $service = new SourceEvidenceFailingGenerationService(
            Mockery::mock(AIProviderService::class),
            Mockery::mock(EntitlementService::class),
        );
        $providers = new Collection([
            $this->provider('openai'),
            $this->provider('claude'),
            $this->provider('gemini'),
            $this->provider('groq'),
            $this->provider('openrouter'),
            $this->provider('ollama'),
        ]);
        $context = new PipelineContext(new PipelineRun, new ContentPipeline);

        try {
            $service->generateWithFailover($context, $providers);
            $this->fail('Expected source verification to stop generation.');
        } catch (SourceEvidenceException $e) {
            $this->assertStringContainsString('source article body', $e->getMessage());
        }

        $this->assertSame(1, $service->attempts);
        foreach ($providers as $provider) {
            $this->assertSame('healthy', $provider->status);
            $this->assertTrue($provider->is_enabled);
            $this->assertSame(0, $provider->error_count);
        }
    }

    private function provider(string $key): AIProvider
    {
        return new AIProvider([
            'provider_key' => $key,
            'name' => ucfirst($key),
            'api_key' => 'test-key',
            'status' => 'healthy',
            'is_enabled' => true,
            'error_count' => 0,
        ]);
    }
}

final class SourceEvidenceFailingGenerationService extends ContentGenerationService
{
    public int $attempts = 0;

    protected function runPipelineStages(PipelineContext $context): PipelineContext
    {
        $this->attempts++;

        throw new SourceEvidenceException(
            'Source verification failed: no source article body could be retrieved.'
        );
    }
}
