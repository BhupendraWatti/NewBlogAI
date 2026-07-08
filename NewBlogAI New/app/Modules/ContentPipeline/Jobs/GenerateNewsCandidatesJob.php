<?php

namespace App\Modules\ContentPipeline\Jobs;

use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\ContentPipeline\Services\NewsDiscoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Coverage discovery workload: produces 9 news candidates for a discovery
 * run, then stops at status 'ready' pending employee selection. Kept
 * separate from ProcessPipelineJob, which executes the full 10-stage
 * generation pipeline with a different failure/retry profile.
 */
class GenerateNewsCandidatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Discovery failures are surfaced on the run; retries go through the retry endpoint. */
    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        protected int $runId
    ) {}

    public function handle(NewsDiscoveryService $discovery): void
    {
        $run = PipelineRun::with('pipeline')->find($this->runId);

        if (! $run || ! $run->pipeline) {
            Log::error("GenerateNewsCandidatesJob failed: Run ID {$this->runId} or associated pipeline not found.");

            return;
        }

        if ($run->status === 'cancelled') {
            Log::info("GenerateNewsCandidatesJob ID {$this->runId} execution was cancelled. Aborting.");

            return;
        }

        Log::info("Coverage discovery started for pipeline ID {$run->pipeline_id} (Run ID {$this->runId}).");

        // NewsDiscoveryService owns run status transitions and failure marking.
        $discovery->discover($run);
    }
}
