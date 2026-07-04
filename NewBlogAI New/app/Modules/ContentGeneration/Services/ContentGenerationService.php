<?php

namespace App\Modules\ContentGeneration\Services;

use App\Modules\AIProviderManager\Services\AIProviderService;
use App\Modules\ContentGeneration\Models\AIRequestLog;
use App\Modules\ContentGeneration\Models\ContentRevision;
use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\ContentPipeline\Models\PipelineRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use App\Modules\SubscriptionManager\Services\EntitlementService;

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
        $query = GeneratedContent::query()->with(['site', 'topic', 'pipeline']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
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
        if (!$pipeline) {
            throw new InvalidArgumentException("Pipeline run has no associated pipeline config.");
        }

        // 1. Gather all variables
        $site = $pipeline->site;
        $topic = $pipeline->topic;
        $promptTemplate = $pipeline->prompt;
        $provider = $pipeline->provider;

        if (!$site || !$topic || !$promptTemplate || !$provider) {
            throw new InvalidArgumentException("Pipeline configuration dependencies are incomplete.");
        }

        $this->entitlements->assertProviderAvailable($site, $provider->provider_key);
        $reservation = $this->entitlements->reserveGeneration(
            $site,
            $provider->provider_key,
            $provider->default_model ?? 'unknown',
            $promptTemplate->id,
            $topic->id,
        );

        // 2. Prepare variables map
        $variables = [
            'topic'    => $topic->name,
            'category' => $topic->category ?? '',
            'language' => $pipeline->language,
            'website'  => $site->domain_url,
        ];

        $compiledPrompt = $this->compilePrompt($promptTemplate->promt, $variables);

        // 3. Trigger provider execution
        $startTime = microtime(true);
        
        try {
            $client = $this->providerService->getDriver($provider->provider_key);
            
            // Decrypt key
            $apiKey = $provider->api_key;
            if (empty($apiKey)) {
                throw new \RuntimeException("API key for provider '{$provider->name}' is missing.");
            }

            // Run generation
            $result = $client->generate($apiKey, $compiledPrompt, $provider->default_model);

            $executionTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            return DB::transaction(function () use ($pipeline, $topic, $site, $promptTemplate, $provider, $result, $executionTimeMs, $run, $reservation) {
                // Determine title from prompt/content or default to Topic + Date
                $title = "Article: {$topic->name} - " . now()->format('Y-m-d');
                $content = $result['text'];

                // 4. Create Generated Content record
                $generatedContent = GeneratedContent::create([
                    'pipeline_id' => $pipeline->id,
                    'site_id'     => $site->id,
                    'topic_id'    => $topic->id,
                    'title'       => $title,
                    'content'     => $content,
                    'status'      => 'draft', // draft by default for human review
                    'metadata'    => [
                        'prompt_id'       => $promptTemplate->id,
                        'prompt_tokens'   => $result['prompt_tokens'],
                        'completion_tokens'=> $result['completion_tokens'],
                        'total_tokens'    => $result['total_tokens'],
                        'cost'            => $result['estimated_cost'],
                    ]
                ]);

                // 5. Save initial content revision
                ContentRevision::create([
                    'generated_content_id' => $generatedContent->id,
                    'title'                => $title,
                    'content'              => $content,
                    'user_id'              => null, // generated by system
                ]);

                // 6. Log AI request history
                $requestLogData = [
                    'customer_id'       => $site->customer_id,
                    'subscription_id'   => $reservation?->subscription_id,
                    'site_id'           => $site->id,
                    'provider'          => $provider->provider_key,
                    'model'             => $provider->default_model ?? 'unknown',
                    'prompt_id'         => $promptTemplate->id,
                    'topic_id'          => $topic->id,
                    'execution_time_ms' => $executionTimeMs,
                    'prompt_tokens'     => $result['prompt_tokens'],
                    'completion_tokens' => $result['completion_tokens'],
                    'total_tokens'      => $result['total_tokens'],
                    'estimated_cost'    => $result['estimated_cost'],
                    'status'            => 'success',
                    'response_metadata' => $result['raw_response'],
                    'error_log'         => null,
                ];

                $reservation
                    ? $reservation->update($requestLogData)
                    : AIRequestLog::create($requestLogData);

                // Update run status
                $run->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
                $pipeline->update(['status' => 'completed']);

                return $generatedContent;
            });

        } catch (\Exception $e) {
            $executionTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            // Log failed AI request
            $requestLogData = [
                'customer_id'       => $site->customer_id,
                'subscription_id'   => $reservation?->subscription_id,
                'site_id'           => $site->id,
                'provider'          => $provider->provider_key,
                'model'             => $provider->default_model ?? 'unknown',
                'prompt_id'         => $promptTemplate->id,
                'topic_id'          => $topic->id,
                'execution_time_ms' => $executionTimeMs,
                'status'            => 'failed',
                'error_log'         => $e->getMessage(),
            ];

            $reservation
                ? $reservation->update($requestLogData)
                : AIRequestLog::create($requestLogData);

            $run->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
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
                    'title'                => $content->title,
                    'content'              => $content->content,
                    'user_id'              => $userId,
                ]);

                return $content;
            });
        } catch (\Exception $e) {
            Log::error("Failed to update generated content: " . $e->getMessage());
            throw new \RuntimeException("Could not save edited content revision.", 0, $e);
        }
    }

    /**
     * Update approval status of generated content.
     */
    public function updateStatus(GeneratedContent $content, string $status): GeneratedContent
    {
        $allowed = ['draft', 'pending_review', 'approved', 'rejected', 'published'];
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException("Invalid content approval status: {$status}");
        }

        $content->update(['status' => $status]);
        return $content;
    }
}
