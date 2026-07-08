<?php

namespace App\Modules\Publishing\Jobs;

use App\Modules\Operations\Notifications\PublishingFailedNotification;
use App\Modules\Publishing\Models\PublishingLog;
use App\Modules\Publishing\Services\PublishingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

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

                return;
            } else {
                $logRecord->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'error_message' => "All {$this->tries} attempts failed. Error: ".$e->getMessage(),
                ]);

                $workflowService = app(\App\Modules\ContentGeneration\Services\WorkflowService::class);
                $workflowService->transitionTo($logRecord->content, 'failed', $logRecord->user_id);
                $workflowService->transitionTo($logRecord->content, 'draft', $logRecord->user_id);

                // Notify admins of the customer that owns this site.
                try {
                    $site = $logRecord->site;
                    if ($site && $site->customer_id) {
                        $adminUsers = \App\Models\User::where('customer_id', $site->customer_id)
                            ->whereIn('role', [1, 2])
                            ->get();
                        if ($adminUsers->isNotEmpty()) {
                            Notification::send($adminUsers, new PublishingFailedNotification($logRecord));
                        }
                    }
                } catch (\Throwable $notifyEx) {
                    Log::warning('PublishPostJob: Could not dispatch PublishingFailedNotification — '.$notifyEx->getMessage());
                }
            }

            throw $e;
        }
    }
}
