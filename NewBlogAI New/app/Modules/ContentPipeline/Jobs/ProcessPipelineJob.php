<?php

namespace App\Modules\ContentPipeline\Jobs;

use App\Modules\ContentGeneration\Services\ContentGenerationService;
use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\MediaManager\Services\ContentPostProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int $runId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ContentGenerationService $generationService): void
    {
        $run = PipelineRun::with('pipeline')->find($this->runId);
        if (! $run || ! $run->pipeline) {
            Log::error("ProcessPipelineJob failed: Run ID {$this->runId} or associated pipeline not found.");

            return;
        }

        $pipeline = $run->pipeline;

        if ($run->status === 'cancelled') {
            Log::info("ProcessPipelineJob ID {$this->runId} execution was cancelled. Aborting.");

            return;
        }

        try {
            // Update status to processing
            $run->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);
            $pipeline->update(['status' => 'processing']);

            Log::info("Content Pipeline ID {$pipeline->id} execution started (Run ID {$this->runId}).");

            // Perform AI Content Generation using interchangeable drivers
            $generatedContent = $generationService->generateContentForRun($run);

            // Post-process the generated content (Markdown to HTML, featured image generation)
            $postProcessor = app(ContentPostProcessor::class);
            $postProcessor->process($generatedContent);

            Log::info("Content Pipeline ID {$pipeline->id} completed successfully via Generation Engine.");

        } catch (\Exception $e) {
            Log::error('Content Pipeline execution failed: '.$e->getMessage());

            $run->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            $pipeline->update(['status' => 'failed']);

            throw $e;
        }
    }
}
