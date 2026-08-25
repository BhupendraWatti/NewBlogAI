<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Support;

use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\ContentGeneration\Models\AIRequestLog;
use App\Modules\ContentPipeline\Models\PipelineRun;
use RuntimeException;

/**
 * Owns the execution deadline and provider-reported usage ledger for one
 * discovery run. Monetary values are estimates unless the Adapter supplies a
 * provider-native billed cost.
 */
final class DiscoveryRunTelemetry
{
    private float $startedAt;

    private array $tokens = ['prompt' => 0, 'completion' => 0, 'total' => 0];

    private float $estimatedCostUsd = 0.0;

    private int $requestsCompleted = 0;

    private array $providerAttempts = [];

    public function __construct(
        private PipelineRun $run,
        private ?AIRequestLog $reservation,
        private int $timeoutSeconds,
    ) {
        $this->startedAt = hrtime(true) / 1_000_000_000;
        $this->persist('starting');
    }

    public function remainingSeconds(): int
    {
        return max(0, (int) ceil($this->timeoutSeconds - $this->elapsedSeconds()));
    }

    public function assertWithinDeadline(): void
    {
        if ($this->remainingSeconds() <= 0) {
            throw new RuntimeException("Discovery request timed out after {$this->timeoutSeconds} seconds.");
        }
    }

    public function beginAttempt(AIProvider $provider, int $attempt, string $stage = 'provider_request'): void
    {
        $this->assertWithinDeadline();
        $this->providerAttempts[] = [
            'provider' => $provider->provider_key,
            'model' => $provider->default_model,
            'attempt' => $attempt,
            'status' => 'processing',
        ];
        $this->persist($stage, $provider->provider_key, $provider->default_model, $attempt);
    }

    public function recordResponse(
        string $provider,
        ?string $model,
        array $result,
        string $stage = 'processing_response',
    ): void {
        $this->tokens['prompt'] += (int) ($result['prompt_tokens'] ?? 0);
        $this->tokens['completion'] += (int) ($result['completion_tokens'] ?? 0);
        $this->tokens['total'] += (int) ($result['total_tokens'] ?? 0);
        $this->estimatedCostUsd += (float) ($result['estimated_cost'] ?? 0.0);
        $this->requestsCompleted++;

        $last = array_key_last($this->providerAttempts);
        if ($last !== null) {
            $this->providerAttempts[$last]['status'] = 'response_received';
        }

        $this->persist($stage, $provider, $model);
    }

    public function recordFailure(AIProvider $provider, int $attempt, string $message): void
    {
        $last = array_key_last($this->providerAttempts);
        if ($last !== null) {
            $this->providerAttempts[$last]['status'] = 'failed';
            $this->providerAttempts[$last]['error'] = mb_substr($message, 0, 500);
        }
        $this->persist('provider_failed', $provider->provider_key, $provider->default_model, $attempt);
    }

    public function complete(string $provider): void
    {
        $this->persist('completed', $provider);
    }

    public function fail(string $message): void
    {
        $this->persist('failed', error: mb_substr($message, 0, 1000));
    }

    /** @return array{prompt:int, completion:int, total:int} */
    public function tokens(): array
    {
        return $this->tokens;
    }

    public function estimatedCostUsd(): float
    {
        return $this->estimatedCostUsd;
    }

    private function elapsedSeconds(): float
    {
        return (hrtime(true) / 1_000_000_000) - $this->startedAt;
    }

    private function snapshot(
        string $stage,
        ?string $provider = null,
        ?string $model = null,
        ?int $attempt = null,
        ?string $error = null,
    ): array {
        $elapsedMs = (int) round($this->elapsedSeconds() * 1000);

        return [
            'stage' => $stage,
            'current_provider' => $provider,
            'current_model' => $model,
            'attempt' => $attempt,
            'requests_completed' => $this->requestsCompleted,
            'tokens' => $this->tokens,
            'estimated_cost_usd' => round($this->estimatedCostUsd, 8),
            'cost_accuracy' => 'estimated_from_provider_usage',
            'elapsed_ms' => $elapsedMs,
            'timeout_ms' => $this->timeoutSeconds * 1000,
            'remaining_ms' => max(0, ($this->timeoutSeconds * 1000) - $elapsedMs),
            'provider_attempts' => $this->providerAttempts,
            'error' => $error,
        ];
    }

    private function persist(
        string $stage,
        ?string $provider = null,
        ?string $model = null,
        ?int $attempt = null,
        ?string $error = null,
    ): void {
        $telemetry = $this->snapshot($stage, $provider, $model, $attempt, $error);
        $properties = $this->run->properties ?? [];
        $properties['telemetry'] = $telemetry;
        $this->run->update(['properties' => $properties]);

        $this->reservation?->update([
            'prompt_tokens' => $this->tokens['prompt'] ?: null,
            'completion_tokens' => $this->tokens['completion'] ?: null,
            'total_tokens' => $this->tokens['total'] ?: null,
            'estimated_cost' => $this->estimatedCostUsd,
            'execution_time_ms' => $telemetry['elapsed_ms'],
            'response_metadata' => [
                'run_type' => PipelineRun::TYPE_DISCOVERY,
                'run_id' => $this->run->id,
                'telemetry' => $telemetry,
            ],
        ]);
    }
}
