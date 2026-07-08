<?php

namespace App\Modules\SiteManager\Jobs;

use App\Modules\SiteManager\Events\SiteSyncTriggered;
use App\Modules\SiteManager\Models\Site;
use App\Modules\SiteManager\Services\WPClientService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncSiteDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 15;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 45;

    /**
     * Create a new job instance.
     */
    public function __construct(public Site $site)
    {
        $this->queue = 'site-sync';
    }

    /**
     * Execute the job.
     */
    public function handle(WPClientService $clientService): void
    {
        Log::info("Starting SyncSiteDataJob for Site ID: {$this->site->id}");

        // Update database sync status to "syncing"
        $this->site->update([
            'last_sync_status' => 'syncing',
        ]);

        event(new SiteSyncTriggered($this->site));

        try {
            $clientService->sync($this->site);
            Log::info("SyncSiteDataJob successfully completed for Site ID: {$this->site->id}");
        } catch (\Exception $e) {
            Log::error("SyncSiteDataJob failed for Site ID: {$this->site->id}. Error: ".$e->getMessage());

            // Re-throw to trigger retry mechanism if attempts remain
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->site->update([
            'last_sync_status' => 'failed',
        ]);
    }
}
