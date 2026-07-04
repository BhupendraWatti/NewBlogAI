<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;
use App\Modules\Operations\Models\ScheduleLog;
use App\Modules\SiteManager\Models\Site;
use App\Modules\SiteManager\Jobs\SyncSiteDataJob;
use App\Modules\ScheduleManager\Services\ScheduleService;

// 1. Automatic WordPress Synchronization task
Schedule::call(function () {
    $log = ScheduleLog::create(['task_name' => 'WordPress Sync Automation']);
    
    try {
        $sites = Site::where('is_active', true)->get();
        foreach ($sites as $site) {
            SyncSiteDataJob::dispatch($site);
        }
        $log->update(['status' => 'success', 'completed_at' => now()]);
    } catch (\Exception $e) {
        $log->update(['status' => 'failed', 'completed_at' => now(), 'output' => $e->getMessage()]);
    }
})->hourly()->name('wordpress-sync-automation');

// Laravel owns recurring generation schedules; WordPress only refreshes configuration.
Schedule::call(function (ScheduleService $schedules) {
    $log = ScheduleLog::create(['task_name' => 'Backend Content Scheduling']);

    try {
        $processed = $schedules->runDue();
        $log->update([
            'status' => 'success',
            'completed_at' => now(),
            'output' => "Queued {$processed} due content schedules.",
        ]);
    } catch (\Throwable $exception) {
        $log->update([
            'status' => 'failed',
            'completed_at' => now(),
            'output' => $exception->getMessage(),
        ]);

        report($exception);
    }
})->everyMinute()->withoutOverlapping()->name('backend-content-scheduling');

// 2. Cleanup operations logs task (older than 30 days)
Schedule::call(function () {
    $log = ScheduleLog::create(['task_name' => 'Operational Logs Cleanup']);
    
    try {
        $cutoff = now()->subDays(30);
        \App\Modules\Operations\Models\JobLog::where('created_at', '<', $cutoff)->delete();
        \App\Modules\Operations\Models\AuditLog::where('created_at', '<', $cutoff)->delete();
        
        $log->update(['status' => 'success', 'completed_at' => now(), 'output' => 'Cleaned logs older than 30 days successfully.']);
    } catch (\Exception $e) {
        $log->update(['status' => 'failed', 'completed_at' => now(), 'output' => $e->getMessage()]);
    }
})->daily()->name('operational-logs-cleanup');
