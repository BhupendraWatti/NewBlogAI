<?php

namespace App\Modules\Publishing\Jobs;

use App\Modules\Publishing\Models\PublishingLog;
use App\Modules\Publishing\Services\PublishingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int $logId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PublishingService $publishingService): void
    {
        $logRecord = PublishingLog::with(['content', 'site'])->find($this->logId);
        if (! $logRecord) {
            Log::error("PublishPostJob failed: Publishing log ID {$this->logId} not found.");

            return;
        }

        if ($logRecord->status === 'cancelled') {
            Log::info("PublishPostJob ID {$this->logId} was cancelled. Aborting.");

            return;
        }

        try {
            $logRecord->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

            // Execute publishing via service
            $publishingService->executePublish($logRecord);

        } catch (\Exception $e) {
            Log::error("PublishPostJob failed for log ID {$this->logId}: ".$e->getMessage());

            $attempts = $this->attempts();
            if ($attempts < $this->tries) {
                $logRecord->update([
                    'status' => 'retrying',
                    'retry_count' => $attempts,
                    'error_message' => "Attempt {$attempts} failed: ".$e->getMessage(),
                ]);

                // Re-queue with delay
                $this->release($this->backoff);
            } else {
                $logRecord->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'error_message' => "All {$this->tries} attempts failed. Error: ".$e->getMessage(),
                ]);

                $logRecord->content->update(['status' => 'draft']); // fall back to draft
            }

            throw $e;
        }
    }
}
