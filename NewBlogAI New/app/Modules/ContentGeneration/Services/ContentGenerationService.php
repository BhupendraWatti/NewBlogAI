<?php

namespace App\Modules\ContentGeneration\Services;

use App\Modules\AIProviderManager\Services\AIProviderService;
use App\Modules\ContentGeneration\Models\AIRequestLog;
use App\Modules\ContentGeneration\Models\ContentRevision;
use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\SubscriptionManager\Services\EntitlementService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     * Parse variables in prompt templates.
     */
    public function compilePrompt(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace(["@{{{$key}}}", "{{{$key}}}"], (string) $value, $template);
        }

        return $template;
    }

    /**
     * Orchestrate content generation for a pipeline run.
     */
    public function generateContentForRun(PipelineRun $run): GeneratedContent
    {
        $pipeline = $run->pipeline;
        if (! $pipeline) {
            throw new InvalidArgumentException('Pipeline run has no associated pipeline config.');
        }

        // 1. Gather all variables
        $site           = $pipeline->site;
        $promptTemplate = $pipeline->prompt;
        $provider       = $pipeline->provider;

        if (! $site || ! $promptTemplate || ! $provider) {
            throw new InvalidArgumentException('Pipeline configuration dependencies are incomplete.');
        }

        $startTime = microtime(true);
        $reservation = null;

        try {
            // Assert subscription entitlements and quotas before starting
            $this->entitlements->assertCanRunPipeline($site, $pipeline, $run->properties ?? []);

            $this->entitlements->assertProviderAvailable($site, $provider->provider_key);
            $reservation = $this->entitlements->reserveGeneration(
                $site,
                $provider->provider_key,
                $provider->default_model ?? 'unknown',
                $promptTemplate->id,
                null,   // topic_id no longer required — pipeline is category-driven
            );

            // 2. Create a PipelineContext using the PipelineRun
            $context = new \App\Modules\ContentPipeline\DTOs\PipelineContext($run, $pipeline);
            $context->metadata['reservation'] = $reservation;

            // Run Laravel pipeline sequentially through the 7 stages
            $context = \Illuminate\Support\Facades\Pipeline::send($context)
                ->through([
                    function (\App\Modules\ContentPipeline\DTOs\PipelineContext $context, \Closure $next) {
                        if ($context->hasErrors()) {
                            return $next($context);
                        }
                        $context = app(\App\Modules\ContentPipeline\Contracts\TopicResolverInterface::class)->handle($context);
                        return $next($context);
                    },
                    function (\App\Modules\ContentPipeline\DTOs\PipelineContext $context, \Closure $next) {
                        if ($context->hasErrors()) {
                            return $next($context);
                        }
                        $context = app(\App\Modules\ContentPipeline\Contracts\ResearchServiceInterface::class)->handle($context);
                        return $next($context);
                    },
                    function (\App\Modules\ContentPipeline\DTOs\PipelineContext $context, \Closure $next) {
                        if ($context->hasErrors()) {
                            return $next($context);
                        }
                        $context = app(\App\Modules\ContentPipeline\Contracts\SourceCollectorInterface::class)->handle($context);
                        return $next($context);
                    },
                    function (\App\Modules\ContentPipeline\DTOs\PipelineContext $context, \Closure $next) {
                        if ($context->hasErrors()) {
                            return $next($context);
                        }
                        $context = app(\App\Modules\ContentPipeline\Contracts\FactExtractorInterface::class)->handle($context);
                        return $next($context);
                    },
                    function (\App\Modules\ContentPipeline\DTOs\PipelineContext $context, \Closure $next) {
                        if ($context->hasErrors()) {
                            return $next($context);
                        }
                        $context = app(\App\Modules\ContentPipeline\Contracts\ContentGeneratorInterface::class)->handle($context);
                        return $next($context);
                    },
                    function (\App\Modules\ContentPipeline\DTOs\PipelineContext $context, \Closure $next) {
                        if ($context->hasErrors()) {
                            return $next($context);
                        }
                        $context = app(\App\Modules\ContentPipeline\Contracts\TranslationInterface::class)->handle($context);
                        return $next($context);
                    },
                    function (\App\Modules\ContentPipeline\DTOs\PipelineContext $context, \Closure $next) {
                        if ($context->hasErrors()) {
                            return $next($context);
                        }
                        $context = app(\App\Modules\ContentPipeline\Contracts\FactAuditorInterface::class)->handle($context);
                        return $next($context);
                    },
                    function (\App\Modules\ContentPipeline\DTOs\PipelineContext $context, \Closure $next) {
                        if ($context->hasErrors()) {
                            return $next($context);
                        }
                        $context = app(\App\Modules\ContentPipeline\Contracts\SEOServiceInterface::class)->handle($context);
                        return $next($context);
                    },
                    function (\App\Modules\ContentPipeline\DTOs\PipelineContext $context, \Closure $next) {
                        if ($context->hasErrors()) {
                            return $next($context);
                        }
                        $context = app(\App\Modules\ContentPipeline\Contracts\MediaPreparatorInterface::class)->handle($context);
                        return $next($context);
                    },
                    function (\App\Modules\ContentPipeline\DTOs\PipelineContext $context, \Closure $next) {
                        if ($context->hasErrors()) {
                            return $next($context);
                        }
                        $context = app(\App\Modules\ContentPipeline\Contracts\PublishingQueueInterface::class)->handle($context);
                        return $next($context);
                    },
                ])
                ->then(function ($context) {
                    if ($context->hasErrors()) {
                        $allErrors = array_merge(...array_values($context->errors));
                        throw new \RuntimeException('Pipeline execution failed: ' . implode(', ', $allErrors));
                    }
                    return $context;
                });

            // Extract the generated content model from the context
            $generatedContent = $context->metadata['generated_content_model'] ?? null;
            if (!$generatedContent instanceof GeneratedContent) {
                throw new \RuntimeException('Pipeline completed but GeneratedContent model was not resolved or stored in context.');
            }

            return $generatedContent;

        } catch (\Exception $e) {
            $executionTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            // Log failed AI request
            $requestLogData = [
                'customer_id' => $site->customer_id,
                'subscription_id' => $reservation?->subscription_id ?? ($this->entitlements->subscriptionForSite($site)?->id ?? null),
                'site_id' => $site->id,
                'provider' => $provider->provider_key,
                'model' => $provider->default_model ?? 'unknown',
                'prompt_id'      => $promptTemplate->id,
                'topic_id'       => null,   // category-driven — no topic FK
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

            throw $e;
        }
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
