<?php

namespace App\Modules\ContentPipeline\Services;

use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\ContentPipeline\Jobs\ProcessPipelineJob;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\SiteManager\Models\Site;
use App\Modules\TopicManager\Models\Topic;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use App\Modules\SubscriptionManager\Services\EntitlementService;

class PipelineService
{
    public function __construct(protected EntitlementService $entitlements) {}

    /**
     * Get paginated pipelines with optional filters.
     */
    public function getPaginated(array $filters, int $limit = 15): LengthAwarePaginator
    {
        $query = ContentPipeline::query()->with(['site', 'topic', 'prompt', 'provider']);

        if (!empty($filters['site_id'])) {
            $query->where('site_id', $filters['site_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate($limit);
    }

    /**
     * Create a new content pipeline. Validates structural dependencies.
     */
    public function createPipeline(array $data): ContentPipeline
    {
        $this->validateDependencies($data);

        try {
            $pipeline = ContentPipeline::create($data);
            return $pipeline->refresh();
        } catch (\Exception $e) {
            Log::error("Failed to create content pipeline config: " . $e->getMessage());
            throw new \RuntimeException("Could not register content pipeline.", 0, $e);
        }
    }

    /**
     * Update an existing pipeline config.
     */
    public function updatePipeline(ContentPipeline $pipeline, array $data): ContentPipeline
    {
        // Merge current data with update payload to validate full state dependencies
        $merged = array_merge($pipeline->toArray(), $data);
        $this->validateDependencies($merged);

        try {
            $pipeline->update($data);
            return $pipeline;
        } catch (\Exception $e) {
            Log::error("Failed to update content pipeline: " . $e->getMessage());
            throw new \RuntimeException("Could not update content pipeline configuration.", 0, $e);
        }
    }

    /**
     * Trigger execution run for a pipeline.
     */
    public function triggerRun(ContentPipeline $pipeline): PipelineRun
    {
        if (!$pipeline->is_active) {
            throw new InvalidArgumentException("Cannot execute an inactive content pipeline.");
        }

        $pipeline->loadMissing(['site', 'provider']);
        $this->entitlements->assertCanGenerate($pipeline->site);
        $this->entitlements->assertProviderAvailable($pipeline->site, $pipeline->provider->provider_key);

        try {
            return DB::transaction(function () use ($pipeline) {
                // Create execution history entry
                $run = PipelineRun::create([
                    'pipeline_id' => $pipeline->id,
                    'status'      => 'queued',
                ]);

                // Update pipeline status
                $pipeline->update(['status' => 'queued']);

                // Dispatch to queue
                ProcessPipelineJob::dispatch($run->id);

                return $run;
            });
        } catch (\Exception $e) {
            Log::error("Failed to trigger pipeline run: " . $e->getMessage());
            throw new \RuntimeException("Failed to queue pipeline execution run.", 0, $e);
        }
    }

    /**
     * Retry a failed pipeline run.
     */
    public function retryRun(PipelineRun $run): PipelineRun
    {
        if ($run->status !== 'failed') {
            throw new InvalidArgumentException("Can only retry failed runs.");
        }

        $pipeline = $run->pipeline;

        try {
            return DB::transaction(function () use ($pipeline, $run) {
                $newRun = PipelineRun::create([
                    'pipeline_id' => $pipeline->id,
                    'status'      => 'queued',
                    'retry_count' => $run->retry_count + 1,
                ]);

                $pipeline->update(['status' => 'queued']);

                ProcessPipelineJob::dispatch($newRun->id);

                return $newRun;
            });
        } catch (\Exception $e) {
            Log::error("Failed to retry pipeline run ID {$run->id}: " . $e->getMessage());
            throw new \RuntimeException("Failed to queue retry run.", 0, $e);
        }
    }

    /**
     * Cancel a queued or processing pipeline run.
     */
    public function cancelRun(PipelineRun $run): void
    {
        if (in_array($run->status, ['completed', 'failed', 'cancelled'], true)) {
            throw new InvalidArgumentException("Cannot cancel a completed, failed or already cancelled run.");
        }

        try {
            DB::transaction(function () use ($run) {
                $run->update(['status' => 'cancelled']);
                $run->pipeline->update(['status' => 'cancelled']);
            });
        } catch (\Exception $e) {
            Log::error("Failed to cancel pipeline run ID {$run->id}: " . $e->getMessage());
            throw new \RuntimeException("Could not cancel pipeline run.", 0, $e);
        }
    }

    /**
     * Validate active dependencies in the database.
     */
    protected function validateDependencies(array $data): void
    {
        // 1. Validate active site
        $site = Site::find($data['site_id']);
        if (!$site) {
            throw new InvalidArgumentException("Referenced Website does not exist.");
        }
        if (!$site->is_active) {
            throw new InvalidArgumentException("Referenced Website is inactive/disabled.");
        }

        // 2. Validate active topic
        $topic = Topic::find($data['topic_id']);
        if (!$topic) {
            throw new InvalidArgumentException("Referenced Topic does not exist.");
        }
        if ($topic->status !== 'active') {
            throw new InvalidArgumentException("Referenced Topic is not active (currently in '{$topic->status}' status).");
        }

        // 3. Validate prompt template
        $prompt = Prompt::find($data['prompt_id']);
        if (!$prompt) {
            throw new InvalidArgumentException("Referenced Prompt Template does not exist.");
        }
        if ($prompt->status !== 'active') {
            throw new InvalidArgumentException("Referenced Prompt Template is not active.");
        }

        // 4. Validate active AI provider
        $provider = AIProvider::find($data['ai_provider_id']);
        if (!$provider) {
            throw new InvalidArgumentException("Referenced AI Provider does not exist.");
        }
        if (!$provider->is_enabled) {
            throw new InvalidArgumentException("Referenced AI Provider is disabled.");
        }
        if (empty($provider->api_key)) {
            throw new InvalidArgumentException("Referenced AI Provider has no API Key configured.");
        }

        $this->entitlements->assertProviderAvailable($site, $provider->provider_key);

        $subscription = $this->entitlements->subscriptionForSite($site);
        if ($subscription && $topic->subscription_id && $topic->subscription_id !== $subscription->id) {
            throw new InvalidArgumentException('The selected topic is not owned by the website subscription.');
        }

        if ($prompt->topic_id && $prompt->topic_id !== $topic->id) {
            throw new InvalidArgumentException('The selected prompt does not belong to the selected topic.');
        }
    }
}
