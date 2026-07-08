<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Services;

use App\Modules\AIProviderManager\Services\AIProviderService;
use App\Modules\ContentPipeline\Models\NewsCandidate;
use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\SubscriptionManager\Services\EntitlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Coverage discovery stage of the newsroom workflow.
 *
 * Researches current real-world events for the pipeline's news category and
 * persists exactly CANDIDATE_TARGET unique news candidates, then stops the
 * run at status 'ready' so an employee can select ONE candidate for full
 * generation. Never generates complete articles.
 */
class NewsDiscoveryService
{
    /** The newsroom contract: exactly this many candidates per coverage run. */
    public const CANDIDATE_TARGET = 9;

    /** Overgenerate so duplicate filtering can still yield the target. */
    public const OVERGENERATION_COUNT = 12;

    /** Total generation attempts (initial + one retry) before hard failure. */
    public const MAX_ATTEMPTS = 2;

    public function __construct(
        protected AIProviderService $providerService,
        protected DuplicateDetectionService $duplicates,
        protected EntitlementService $entitlements,
    ) {}

    /**
     * Execute discovery for a queued discovery run.
     *
     * @throws RuntimeException on unrecoverable failure (run is marked failed)
     */
    public function discover(PipelineRun $run): void
    {
        if (! $run->isDiscovery()) {
            throw new RuntimeException("Run ID {$run->id} is not a discovery run.");
        }

        $pipeline = $run->pipeline;
        $site = $pipeline?->site;
        $provider = $pipeline?->provider;
        $prompt = $pipeline?->prompt;

        if (! $pipeline || ! $site || ! $provider || ! $prompt) {
            throw new RuntimeException('Discovery run has incomplete pipeline dependencies.');
        }

        $reservation = null;
        $startTime = microtime(true);

        try {
            $run->update(['status' => 'processing', 'started_at' => now()]);

            $this->entitlements->assertCanGenerate($site);
            $this->entitlements->assertProviderAvailable($site, $provider->provider_key);
            $reservation = $this->entitlements->reserveGeneration(
                $site,
                $provider->provider_key,
                $provider->default_model ?? 'unknown',
                $prompt->id,
                null,
            );

            $site->loadMissing('customer');
            $country = $pipeline->target_country ?: ($site->customer?->country ?? null);

            $category = $pipeline->news_category ?? 'global';
            $language = $pipeline->language ?: 'en';

            $excludedTitles = [];
            $unique = [];
            $totalTokens = ['prompt' => 0, 'completion' => 0, 'total' => 0];
            $totalCost = 0.0;

            for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS && count($unique) < self::CANDIDATE_TARGET; $attempt++) {
                $needed = self::OVERGENERATION_COUNT - count($unique);
                $promptText = $this->buildDiscoveryPrompt($category, $language, $needed, array_merge(
                    $excludedTitles,
                    array_column($unique, 'title'),
                ), $country);

                $driver = $this->providerService->getDriver($provider->provider_key);
                $result = $driver->generate($provider->api_key, $promptText, $provider->default_model);

                $totalTokens['prompt'] += (int) ($result['prompt_tokens'] ?? 0);
                $totalTokens['completion'] += (int) ($result['completion_tokens'] ?? 0);
                $totalTokens['total'] += (int) ($result['total_tokens'] ?? 0);
                $totalCost += (float) ($result['estimated_cost'] ?? 0.0);

                $parsed = $this->parseCandidates((string) ($result['text'] ?? ''));
                $filtered = $this->duplicates->filterUnique(array_merge($unique, $parsed), $site->id);

                $unique = array_slice($filtered['unique'], 0, self::CANDIDATE_TARGET + 3);
                $excludedTitles = array_merge($excludedTitles, array_column($filtered['duplicates'], 'title'));

                Log::info('NewsDiscoveryService: attempt completed.', [
                    'run_id' => $run->id,
                    'attempt' => $attempt,
                    'unique_count' => count($unique),
                    'duplicates_dropped' => count($filtered['duplicates']),
                ]);
            }

            if (count($unique) < self::CANDIDATE_TARGET) {
                throw new RuntimeException(sprintf(
                    'Discovery produced only %d unique candidates (target %d) after %d attempts.',
                    count($unique),
                    self::CANDIDATE_TARGET,
                    self::MAX_ATTEMPTS,
                ));
            }

            $unique = array_slice($unique, 0, self::CANDIDATE_TARGET);

            DB::transaction(function () use ($run, $unique) {
                foreach ($unique as $index => $candidate) {
                    NewsCandidate::create([
                        'pipeline_run_id' => $run->id,
                        'position' => $index + 1,
                        'title' => mb_substr(trim((string) $candidate['title']), 0, 500),
                        'summary' => $candidate['summary'] ?? null,
                        'source_references' => $candidate['source_references'] ?? [],
                        'keywords' => $candidate['keywords'] ?? [],
                        'trend_score' => $this->clampScore($candidate['trend_score'] ?? 0),
                        'freshness_score' => $this->clampScore($candidate['freshness_score'] ?? 0),
                        'uniqueness_hash' => NewsCandidate::hashTitle((string) $candidate['title']),
                        'metadata' => ['event_date' => $candidate['event_date'] ?? null],
                        'status' => NewsCandidate::STATUS_CANDIDATE,
                    ]);
                }

                $run->update(['status' => PipelineRun::STATUS_READY]);
            });

            $reservation?->update([
                'prompt_tokens' => $totalTokens['prompt'] ?: null,
                'completion_tokens' => $totalTokens['completion'] ?: null,
                'total_tokens' => $totalTokens['total'] ?: null,
                'estimated_cost' => $totalCost,
                'execution_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'status' => 'success',
                'response_metadata' => ['run_type' => PipelineRun::TYPE_DISCOVERY, 'run_id' => $run->id],
            ]);

            Log::info('NewsDiscoveryService: discovery run ready for employee selection.', [
                'run_id' => $run->id,
                'candidates' => self::CANDIDATE_TARGET,
            ]);
        } catch (\Exception $e) {
            $reservation?->update([
                'status' => 'failed',
                'error_log' => $e->getMessage(),
                'execution_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'response_metadata' => ['run_type' => PipelineRun::TYPE_DISCOVERY, 'run_id' => $run->id],
            ]);

            $run->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Build the discovery prompt: distinct current events, strict JSON output.
     *
     * @param array<int, string> $excludedTitles
     */
    protected function buildDiscoveryPrompt(string $category, string $language, int $count, array $excludedTitles, ?string $country = null): string
    {
        $today = now()->format('F j, Y');
        $exclusions = '';

        if (! empty($excludedTitles)) {
            $exclusions = "\nDo NOT include any event that overlaps with these already-covered headlines:\n- "
                .implode("\n- ", array_slice($excludedTitles, 0, 40));
        }

        $regionContext = $country ? " focusing specifically on national news events relevant to or occurring in {$country}" : '';

        return <<<PROMPT
You are a newsroom research editor. Today is {$today}.

Research the most significant CURRENT real-world news events in the "{$category}" category from the last 48 hours{$regionContext} and identify exactly {$count} DIFFERENT events. Each item must describe a DISTINCT real-world event — no two items may cover the same story, announcement, match, or incident.

Write the "title" and "summary" of each item in this language: {$language}.

Respond with ONLY a valid JSON array (no markdown fences, no commentary) of exactly {$count} objects, each with these fields:
- "title": concise news headline (max 120 characters)
- "summary": 2-3 sentence factual summary of the event
- "source_references": array of 1-3 objects {"name": "trusted outlet name", "url": "https://..."}
- "keywords": array of 3-6 lowercase keywords
- "trend_score": integer 0-100 (how much current attention the event has)
- "freshness_score": integer 0-100 (100 = happened in the last few hours)
- "event_date": ISO 8601 date of the event
{$exclusions}
PROMPT;
    }

    /**
     * Parse the AI response into candidate arrays. Tolerates code fences and
     * surrounding prose; requires a JSON array of objects with titles.
     *
     * @return array<int, array>
     */
    protected function parseCandidates(string $text): array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?|```$/m', '', $text) ?? $text;

        $start = strpos($text, '[');
        $end = strrpos($text, ']');
        if ($start === false || $end === false || $end <= $start) {
            throw new RuntimeException('Discovery response did not contain a JSON array.');
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Discovery response JSON could not be parsed: '.json_last_error_msg());
        }

        $candidates = [];
        foreach ($decoded as $item) {
            if (! is_array($item) || trim((string) ($item['title'] ?? '')) === '') {
                continue;
            }
            $candidates[] = [
                'title' => trim((string) $item['title']),
                'summary' => isset($item['summary']) ? trim((string) $item['summary']) : null,
                'source_references' => is_array($item['source_references'] ?? null) ? $item['source_references'] : [],
                'keywords' => is_array($item['keywords'] ?? null) ? array_values($item['keywords']) : [],
                'trend_score' => $item['trend_score'] ?? 0,
                'freshness_score' => $item['freshness_score'] ?? 0,
                'event_date' => $item['event_date'] ?? null,
            ];
        }

        if (empty($candidates)) {
            throw new RuntimeException('Discovery response contained no usable candidates.');
        }

        return $candidates;
    }

    protected function clampScore(mixed $value): int
    {
        return max(0, min(100, (int) $value));
    }
}
