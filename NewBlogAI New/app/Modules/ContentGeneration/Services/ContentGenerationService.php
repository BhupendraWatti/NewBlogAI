<?php

namespace App\Modules\ContentGeneration\Services;

use App\Models\User;
use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\AIProviderManager\Services\AIProviderService;
use App\Modules\AIProviderManager\Support\ProviderErrorClassifier;
use App\Modules\ContentGeneration\Models\AIRequestLog;
use App\Modules\ContentGeneration\Models\ContentRevision;
use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\ContentPipeline\Contracts\ChronologicalContextParserInterface;
use App\Modules\ContentPipeline\Contracts\ContentGeneratorInterface;
use App\Modules\ContentPipeline\Contracts\FactAuditorInterface;
use App\Modules\ContentPipeline\Contracts\FactExtractorInterface;
use App\Modules\ContentPipeline\Contracts\MediaPreparatorInterface;
use App\Modules\ContentPipeline\Contracts\PublishingQueueInterface;
use App\Modules\ContentPipeline\Contracts\ResearchServiceInterface;
use App\Modules\ContentPipeline\Contracts\SEOServiceInterface;
use App\Modules\ContentPipeline\Contracts\SourceCollectorInterface;
use App\Modules\ContentPipeline\Contracts\TopicResolverInterface;
use App\Modules\ContentPipeline\Contracts\TranslationInterface;
use App\Modules\ContentPipeline\DTOs\PipelineContext;
use App\Modules\ContentPipeline\Exceptions\DuplicateNewsException;
use App\Modules\ContentPipeline\Exceptions\SourceEvidenceException;
use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\ContentPipeline\Services\DuplicateDetectionService;
use App\Modules\ContentPipeline\Support\PipelineErrorFormatter;
use App\Modules\Operations\Notifications\AIGenerationFailedNotification;
use App\Modules\SubscriptionManager\Services\EntitlementService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Pipeline;
use InvalidArgumentException;

class ContentGenerationService
{
    public function __construct(
        protected AIProviderService $providerService,
        protected EntitlementService $entitlements,
    ) {}

    /**
     * Get paginated generated articles list.
     */
    public function getPaginated(array $filters, int $limit = 15): LengthAwarePaginator
    {
        $query = GeneratedContent::query()->with(['site', 'pipeline']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['customer_id'])) {
            $query->whereHas('site', function ($q) use ($filters) {
                $q->where('customer_id', $filters['customer_id']);
            });
        }

        return $query->latest()->paginate($limit);
    }

    /**
     * Orchestrate content generation for a pipeline run.
     *
     * Tries each available AI provider in cost order. Each provider gets up to
     * FAILOVER_MAX_ATTEMPTS attempts with exponential back-off (2 s → 4 s → 8 s)
     * before the next provider is tried. The pipeline's own configured provider
     * is always placed first in the failover list.
     */
    public function generateContentForRun(PipelineRun $run): GeneratedContent
    {
        @set_time_limit(300); // 5 minutes execution limit for full article generation

        $pipeline = $run->pipeline;
        if (! $pipeline) {
            throw new InvalidArgumentException('Pipeline run has no associated pipeline config.');
        }

        $site = $pipeline->site;
        $promptTemplate = $pipeline->prompt;
        $provider = $pipeline->provider;  // preferred provider — tried first

        if (! $site || ! $promptTemplate || ! $provider) {
            throw new InvalidArgumentException('Pipeline configuration dependencies are incomplete.');
        }

        $startTime = microtime(true);
        $reservation = null;

        try {
            // Assert subscription entitlements and quotas before starting
            $this->entitlements->assertCanRunPipeline($site, $pipeline, $run->properties ?? []);

            // Reserve against the pipeline's preferred provider; the failover
            // loop may end up using a different provider, but the reservation
            // represents the intent — cost accounting is updated on completion.
            $this->entitlements->assertProviderAvailable($site, $provider->provider_key);
            $reservation = $this->entitlements->reserveGeneration(
                $site,
                $provider->provider_key,
                $provider->default_model ?? 'unknown',
                $promptTemplate->id,
                null,   // topic_id no longer required — pipeline is category-driven
                $provider->id
            );

            // Build the initial PipelineContext (provider injected per-attempt inside failover)
            $context = new PipelineContext($run, $pipeline);
            $context->metadata['reservation'] = $reservation;

            // Newsroom workflow: propagate the employee-selected news candidate
            // so generation is anchored to it. Absent for legacy runs (BC-safe).
            $selectedCandidate = $run->properties['selected_candidate'] ?? null;
            if (is_array($selectedCandidate) && ! empty($selectedCandidate['title'])) {
                $context->metadata['selected_news'] = $selectedCandidate;

                foreach ((array) ($selectedCandidate['source_references'] ?? []) as $reference) {
                    if (empty($reference['url'])) {
                        continue;
                    }
                    $context->addSource([
                        'url' => (string) $reference['url'],
                        'title' => (string) ($reference['name'] ?? $selectedCandidate['title']),
                        'snippet' => (string) ($selectedCandidate['summary'] ?? ''),
                        'publisher' => $reference['name'] ?? null,
                        'relevance_score' => 0.9,
                        'keywords' => array_values(array_filter(array_map('strval', (array) ($selectedCandidate['keywords'] ?? [])))),
                        'metadata' => ['origin' => 'news_candidate'],
                    ]);
                }
            }

            // ── Run with automatic provider failover ─────────────────────────
            $availableProviders = $this->getAvailableProviders($provider, $site->customer_id);
            [$generatedContent, $usedProvider] = $this->generateWithFailover($context, $availableProviders);

            // Update reservation with the provider that actually succeeded
            $reservation?->update([
                'provider' => $usedProvider->provider_key,
                'provider_id' => $usedProvider->id,
                'model' => $usedProvider->default_model ?? 'unknown',
                'status' => 'success',
                'execution_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
            ]);

            return $generatedContent;

        } catch (\Exception $e) {
            $executionTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            // Log failed AI request
            $requestLogData = [
                'customer_id' => $site->customer_id,
                'subscription_id' => $reservation?->subscription_id ?? ($this->entitlements->subscriptionForSite($site)?->id ?? null),
                'site_id' => $site->id,
                'provider' => $provider->provider_key,
                'provider_id' => $provider->id,
                'model' => $provider->default_model ?? 'unknown',
                'prompt_id' => $promptTemplate->id,
                'topic_id' => null,
                'execution_time_ms' => $executionTimeMs,
                'status' => 'failed',
                'error_log' => $e->getMessage(),
            ];

            if ($reservation) {
                $reservation->update($requestLogData);
            } else {
                AIRequestLog::create($requestLogData);
            }

            $run->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            $pipeline->update(['status' => 'failed']);

            // Notify site admins that AI generation failed.
            try {
                if ($site->customer_id) {
                    $adminUsers = User::where('customer_id', $site->customer_id)
                        ->whereIn('role', [1, 2])
                        ->get();
                    if ($adminUsers->isNotEmpty()) {
                        Notification::send($adminUsers, new AIGenerationFailedNotification($run));
                    }
                }
            } catch (\Throwable $notifyEx) {
                Log::warning('ContentGenerationService: Could not dispatch AIGenerationFailedNotification — '.$notifyEx->getMessage());
            }

            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Provider failover helpers
    // ─────────────────────────────────────────────────────────────────────────

    /** Max attempts per provider before moving to the next one. */
    private const FAILOVER_MAX_ATTEMPTS = 3;

    /** Base delay (seconds) for 2^attempt exponential back-off: 2 s, 4 s, 8 s. */
    private const FAILOVER_BASE_DELAY_SECONDS = 2;

    /**
     * Return all enabled providers that have a valid API key, sorted by cost
     * order (Groq → Gemini → OpenAI → Claude → OpenRouter → Ollama).
     *
     * The preferred provider (the pipeline's configured provider) is always
     * placed first so it is tried before any cheaper/faster fallback.
     *
     * @return Collection<int, AIProvider>
     */
    public function getAvailableProviders(?AIProvider $preferred = null, ?string $customerId = null): Collection
    {
        $allEnabled = AIProvider::availableToCustomer($customerId)
            ->where('is_enabled', true)
            ->whereNotNull('api_key')
            ->get()
            ->filter(fn (AIProvider $p) => ! empty($p->api_key));

        // Cooldown check / Auto-recovery
        $healthy = $allEnabled->filter(function (AIProvider $p) {
            $p->checkRecovery();

            return $p->status === 'healthy';
        });

        // Sort by priority ascending, then preferred first
        $sorted = $healthy->sortBy(function (AIProvider $p) use ($preferred, $customerId) {
            $isPreferred = ($preferred && $p->id === $preferred->id) ? 0 : 1;
            $isPlatformFallback = $customerId !== null && $p->customer_id === null ? 1 : 0;

            return [$isPreferred, $isPlatformFallback, $p->priority, $p->id];
        })->values();

        return $sorted;
    }

    /**
     * Run the full Laravel content-generation pipeline with automatic provider
     * failover.
     *
     * Each provider in $providers receives up to FAILOVER_MAX_ATTEMPTS attempts
     * with 2 s / 4 s / 8 s exponential back-off between retries. On the first
     * successful attempt the generated content model is returned along with the
     * provider key that produced it. If every provider exhausts all attempts a
     * descriptive RuntimeException is thrown that lists every error.
     *
     * The failover works by injecting the current provider into
     * PipelineContext::$overrideProvider before each attempt. The
     * ContentGeneratorService stage reads this override instead of the
     * pipeline's configured provider, keeping the pipeline model untouched.
     *
     * @param  Collection<int, AIProvider>  $providers
     * @return array{0: GeneratedContent, 1: AIProvider} [content, usedProvider (the AIProvider object that succeeded)]
     *
     * @throws \RuntimeException when ALL providers fail
     */
    public function generateWithFailover(PipelineContext $context, Collection $providers): array
    {
        $allErrors = [];

        foreach ($providers as $provider) {
            $providerKey = $provider->provider_key;

            for ($attempt = 1; $attempt <= self::FAILOVER_MAX_ATTEMPTS; $attempt++) {
                try {
                    Log::info('ContentGenerationService: Trying provider.', [
                        'run_id' => $context->run->id,
                        'provider' => $providerKey,
                        'provider_id' => $provider->id,
                        'attempt' => $attempt,
                        'max_attempts' => self::FAILOVER_MAX_ATTEMPTS,
                    ]);

                    // Clone context and inject the current failover provider so
                    // ContentGeneratorService uses it instead of pipeline->provider.
                    $attemptContext = clone $context;
                    $attemptContext->overrideProvider = $provider;
                    $attemptContext->errors = []; // clear any errors from prior stages

                    // Run the full pipeline stage chain
                    $attemptContext = $this->runPipelineStages($attemptContext);

                    $generatedContent = $attemptContext->metadata['generated_content_model'] ?? null;
                    if (! $generatedContent instanceof GeneratedContent) {
                        throw new \RuntimeException('Pipeline completed but GeneratedContent model was not resolved.');
                    }

                    Log::info('ContentGenerationService: Provider succeeded.', [
                        'run_id' => $context->run->id,
                        'provider' => $providerKey,
                        'provider_id' => $provider->id,
                        'attempt' => $attempt,
                    ]);

                    // Mark provider success
                    $provider->handleSuccess();

                    return [$generatedContent, $provider];

                } catch (DuplicateNewsException $e) {
                    // Rethrow immediately to abort the failover loop (no point retrying other providers for duplicates)
                    throw $e;
                } catch (SourceEvidenceException $e) {
                    // Source access happens before the AI call and is shared by
                    // every provider. Do not blame, disable, or retry providers.
                    throw $e;
                } catch (\Exception $e) {
                    $errorMsg = $e->getMessage();
                    $allErrors[$providerKey][] = "attempt {$attempt}: {$errorMsg}";

                    $provider->handleFailure($e);

                    Log::warning('ContentGenerationService: Provider attempt failed.', [
                        'run_id' => $context->run->id,
                        'provider' => $providerKey,
                        'provider_id' => $provider->id,
                        'attempt' => $attempt,
                        'error' => $errorMsg,
                    ]);

                    // Permanent failures (bad key, connection refused, bad
                    // request) can never succeed on retry. Retrying them re-runs
                    // the full pipeline stage chain and burns real tokens/quota,
                    // so fail over to the next provider immediately instead.
                    // Rate limits are also skipped here: the driver already
                    // applied its own back-off, and the provider won't reset
                    // within our budget, so move straight to the next one.
                    if (! ProviderErrorClassifier::shouldRetrySameProvider($e)) {
                        Log::info('ContentGenerationService: Not retrying this provider, moving to next.', [
                            'run_id' => $context->run->id,
                            'provider' => $providerKey,
                            'provider_id' => $provider->id,
                            'reason' => ProviderErrorClassifier::reason($e),
                        ]);
                        break;
                    }

                    // Exponential back-off between retries (not after the last attempt)
                    if ($attempt < self::FAILOVER_MAX_ATTEMPTS) {
                        $delaySeconds = self::FAILOVER_BASE_DELAY_SECONDS ** $attempt; // 2, 4, 8
                        Log::debug("ContentGenerationService: Backing off {$delaySeconds}s before next attempt.");
                        sleep($delaySeconds);
                    }
                }
            }

            Log::warning('ContentGenerationService: All attempts failed for provider, moving to next.', [
                'run_id' => $context->run->id,
                'provider' => $providerKey,
                'provider_id' => $provider->id,
                'errors' => $allErrors[$providerKey] ?? [],
            ]);
        }

        // Every provider failed — build a descriptive exception
        throw new \RuntimeException(
            PipelineErrorFormatter::format($allErrors, 'Content generation')
        );
    }

    /**
     * Run the ordered Laravel pipeline stages and return the final context.
     *
     * Extracted into its own method so generateWithFailover() can call it
     * cleanly per-attempt without duplicating the stage list.
     *
     * @throws \RuntimeException if any stage records an error
     */
    protected function runPipelineStages(PipelineContext $context): PipelineContext
    {
        return Pipeline::send($context)
            ->through([
                function (PipelineContext $context, \Closure $next) {
                    if ($context->hasErrors()) {
                        return $next($context);
                    }
                    $context = app(TopicResolverInterface::class)->handle($context);

                    return $next($context);
                },
                function (PipelineContext $context, \Closure $next) {
                    if ($context->hasErrors()) {
                        return $next($context);
                    }
                    $context = app(ResearchServiceInterface::class)->handle($context);

                    return $next($context);
                },
                function (PipelineContext $context, \Closure $next) {
                    if ($context->hasErrors()) {
                        return $next($context);
                    }
                    $context = app(SourceCollectorInterface::class)->handle($context);

                    return $next($context);
                },
                function (PipelineContext $context, \Closure $next) {
                    if ($context->hasErrors()) {
                        return $next($context);
                    }

                    // Duplicate check for automated/scheduled runs (runs without manual selection)
                    $selectedNews = $context->metadata['selected_news'] ?? null;
                    if (! is_array($selectedNews) && ! empty($context->sources)) {
                        $topSource = $context->sources[0] ?? null;
                        if ($topSource) {
                            $title = $topSource->title ?? '';
                            $keywords = $topSource->keywords ?? [];
                            $duplicatesService = app(DuplicateDetectionService::class);

                            if ($duplicatesService->isDuplicate((string) $title, (array) $keywords, $context->pipeline->site_id)) {
                                Log::info("ContentGenerationService: Pipeline run aborted. News event '{$title}' is duplicate of recently published content.", [
                                    'title' => $title,
                                    'site_id' => $context->pipeline->site_id,
                                    'run_id' => $context->run->id,
                                ]);
                                throw new DuplicateNewsException(
                                    "This news event ('{$title}') has already been covered recently on this website."
                                );
                            }
                        }
                    }

                    return $next($context);
                },
                function (PipelineContext $context, \Closure $next) {
                    if ($context->hasErrors()) {
                        return $next($context);
                    }
                    $context = app(FactExtractorInterface::class)->handle($context);

                    return $next($context);
                },
                // ── Temporal Context Analysis ────────────────────────────────
                // Decouples HTML publication timestamp from root event time.
                // Tags story_type ('breaking' | 'followup' | 'background') and
                // injects a temporal framing guardrail into dynamic_instructions
                // so the AI writer does not mis-frame follow-up stories as breaking news.
                // Non-blocking: any failure here is logged and the pipeline continues.
                function (PipelineContext $context, \Closure $next) {
                    $context = app(ChronologicalContextParserInterface::class)->handle($context);

                    return $next($context);
                },
                function (PipelineContext $context, \Closure $next) {
                    if ($context->hasErrors()) {
                        return $next($context);
                    }
                    $context = app(ContentGeneratorInterface::class)->handle($context);

                    return $next($context);
                },
                function (PipelineContext $context, \Closure $next) {
                    if ($context->hasErrors()) {
                        return $next($context);
                    }
                    $context = app(TranslationInterface::class)->handle($context);

                    return $next($context);
                },
                function (PipelineContext $context, \Closure $next) {
                    if ($context->hasErrors()) {
                        return $next($context);
                    }
                    $context = app(FactAuditorInterface::class)->handle($context);

                    return $next($context);
                },
                function (PipelineContext $context, \Closure $next) {
                    if ($context->hasErrors()) {
                        return $next($context);
                    }
                    $context = app(SEOServiceInterface::class)->handle($context);

                    return $next($context);
                },
                function (PipelineContext $context, \Closure $next) {
                    if ($context->hasErrors()) {
                        return $next($context);
                    }
                    $context = app(MediaPreparatorInterface::class)->handle($context);

                    return $next($context);
                },
                function (PipelineContext $context, \Closure $next) {
                    if ($context->hasErrors()) {
                        return $next($context);
                    }
                    $context = app(PublishingQueueInterface::class)->handle($context);

                    return $next($context);
                },
            ])
            ->then(function (PipelineContext $context) {
                if ($context->hasErrors()) {
                    if (! empty($context->errors['source_evidence'])) {
                        throw new SourceEvidenceException(
                            'Source verification failed before the AI provider was called; the API key and quota were not involved: '
                            .implode(', ', $context->errors['source_evidence'])
                        );
                    }

                    $allErrors = array_merge(...array_values($context->errors));
                    throw new \RuntimeException('Pipeline execution failed: '.implode(', ', $allErrors));
                }

                return $context;
            });
    }

    /**
     * Manually edit generated content and create a revision.
     */
    public function updateContent(GeneratedContent $content, array $data, ?int $userId = null): GeneratedContent
    {
        try {
            return DB::transaction(function () use ($content, $data, $userId) {
                $content->update($data);

                // Create a revision when editing content
                ContentRevision::create([
                    'generated_content_id' => $content->id,
                    'title' => $content->title,
                    'content' => $content->content,
                    'user_id' => $userId,
                ]);

                return $content;
            });
        } catch (\Exception $e) {
            Log::error('Failed to update generated content: '.$e->getMessage());
            throw new \RuntimeException('Could not save edited content revision.', 0, $e);
        }
    }

    /**
     * Update approval status of generated content.
     */
    public function updateStatus(GeneratedContent $content, string $status): GeneratedContent
    {
        $allowed = ['draft', 'pending_review', 'approved', 'rejected', 'published'];
        if (! in_array($status, $allowed, true)) {
            throw new InvalidArgumentException("Invalid content approval status: {$status}");
        }

        $content->update(['status' => $status]);

        return $content;
    }
}
