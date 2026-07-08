<?php

use App\Models\User;
use App\Modules\CustomerManager\Models\CustomerActivity;
use App\Modules\Operations\Models\AuditLog;
use App\Modules\Operations\Models\JobLog;
use App\Modules\Operations\Models\ScheduleLog;
use App\Modules\Operations\Notifications\QueueStuckNotification;
use App\Modules\Operations\Notifications\SubscriptionExpiredNotification;
use App\Modules\Operations\Notifications\SubscriptionExpiringNotification;
use App\Modules\ScheduleManager\Services\ScheduleService;
use App\Modules\SiteManager\Jobs\SyncSiteDataJob;
use App\Modules\SiteManager\Models\Site;
use App\Modules\SubscriptionManager\Models\Subscription;
use App\Modules\SubscriptionManager\Models\SubscriptionHistory;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 1. Automatic WordPress Synchronization task
Schedule::call(function () {
    $log = ScheduleLog::create(['task_name' => 'WordPress Sync Automation']);

    try {
        $sites = Site::where('is_active', true)->get();
        foreach ($sites as $site) {
            SyncSiteDataJob::dispatch($site);
        }
        $log->update(['status' => 'success', 'completed_at' => now()]);
    } catch (Exception $e) {
        $log->update(['status' => 'failed', 'completed_at' => now(), 'output' => $e->getMessage()]);
    }
})->hourly()->name('wordpress-sync-automation');

// Laravel owns recurring generation schedules; WordPress only refreshes configuration.
Schedule::call(function (ScheduleService $schedules) {
    $log = ScheduleLog::create(['task_name' => 'Backend Content Scheduling']);

    try {
        $processed = $schedules->runDue();
        $log->update([
            'status'       => 'success',
            'completed_at' => now(),
            'output'       => "Queued {$processed} due content schedules.",
        ]);
    } catch (Throwable $exception) {
        $log->update([
            'status'       => 'failed',
            'completed_at' => now(),
            'output'       => $exception->getMessage(),
        ]);

        report($exception);
    }
})->everyMinute()->name('backend-content-scheduling')->withoutOverlapping();

// 2. Cleanup operations logs task (older than 30 days)
Schedule::call(function () {
    $log = ScheduleLog::create(['task_name' => 'Operational Logs Cleanup']);

    try {
        $cutoff = now()->subDays(30);
        JobLog::where('created_at', '<', $cutoff)->delete();
        AuditLog::where('created_at', '<', $cutoff)->delete();

        $log->update(['status' => 'success', 'completed_at' => now(), 'output' => 'Cleaned logs older than 30 days successfully.']);
    } catch (Exception $e) {
        $log->update(['status' => 'failed', 'completed_at' => now(), 'output' => $e->getMessage()]);
    }
})->daily()->name('operational-logs-cleanup');

// 3. Subscription lifecycle automation
//    Runs daily at midnight UTC.
//    - Warns customers 7 days before expiry (trial_ends_at or ends_at).
//    - Transitions trial/active → expired when the deadline has passed.
Schedule::call(function () {
    $log = ScheduleLog::create(['task_name' => 'Subscription Lifecycle Automation']);

    try {
        $now    = now();
        $in7Days = $now->copy()->addDays(7);
        $expired = 0;
        $warned  = 0;

        // ── 3a. Send 7-day expiry warnings ─────────────────────────────────
        // Trial subscriptions expiring in exactly 7 days (within today's window)
        $expiringTrials = Subscription::with(['plan', 'customer'])
            ->where('status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [$now->copy()->addDays(6)->startOfDay(), $now->copy()->addDays(7)->endOfDay()])
            ->get();

        foreach ($expiringTrials as $subscription) {
            try {
                $daysLeft = (int) $now->diffInDays($subscription->trial_ends_at, false);
                $admins   = User::where('customer_id', $subscription->customer_id)
                    ->whereIn('role', [1, 2])->get();
                if ($admins->isNotEmpty()) {
                    Notification::send($admins, new SubscriptionExpiringNotification($subscription, max(1, $daysLeft)));
                    $warned++;
                }
            } catch (Throwable $e) {
                Log::warning("Subscription lifecycle: could not send warning for sub {$subscription->id}: ".$e->getMessage());
            }
        }

        // Paid subscriptions expiring in exactly 7 days
        $expiringPaid = Subscription::with(['plan', 'customer'])
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [$now->copy()->addDays(6)->startOfDay(), $now->copy()->addDays(7)->endOfDay()])
            ->get();

        foreach ($expiringPaid as $subscription) {
            try {
                $daysLeft = (int) $now->diffInDays($subscription->ends_at, false);
                $admins   = User::where('customer_id', $subscription->customer_id)
                    ->whereIn('role', [1, 2])->get();
                if ($admins->isNotEmpty()) {
                    Notification::send($admins, new SubscriptionExpiringNotification($subscription, max(1, $daysLeft)));
                    $warned++;
                }
            } catch (Throwable $e) {
                Log::warning("Subscription lifecycle: could not send warning for sub {$subscription->id}: ".$e->getMessage());
            }
        }

        // ── 3b. Expire overdue trials ──────────────────────────────────────
        $overdueTrials = Subscription::with(['plan', 'customer'])
            ->where('status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', $now)
            ->get();

        foreach ($overdueTrials as $subscription) {
            try {
                DB::transaction(function () use ($subscription, $now) {
                    $subscription->update(['status' => 'expired']);

                    SubscriptionHistory::create([
                        'customer_id'    => $subscription->customer_id,
                        'plan_id'        => $subscription->plan_id,
                        'event_type'     => 'expired',
                        'billing_period' => $subscription->billing_period,
                        'amount_paid'    => 0.00,
                    ]);

                    CustomerActivity::create([
                        'customer_id' => $subscription->customer_id,
                        'event_type'  => 'subscription_expired',
                        'description' => 'Trial period ended — subscription moved to expired.',
                        'properties'  => ['subscription_id' => $subscription->id, 'expired_at' => $now->toDateTimeString()],
                    ]);
                });

                // Notify admins
                $admins = User::where('customer_id', $subscription->customer_id)
                    ->whereIn('role', [1, 2])->get();
                if ($admins->isNotEmpty()) {
                    Notification::send($admins, new SubscriptionExpiredNotification($subscription));
                }

                $expired++;
            } catch (Throwable $e) {
                Log::error("Subscription lifecycle: could not expire trial sub {$subscription->id}: ".$e->getMessage());
            }
        }

        // ── 3c. Expire overdue paid subscriptions ──────────────────────────
        $overdueActive = Subscription::with(['plan', 'customer'])
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $now)
            ->get();

        foreach ($overdueActive as $subscription) {
            try {
                DB::transaction(function () use ($subscription, $now) {
                    $subscription->update(['status' => 'expired']);

                    SubscriptionHistory::create([
                        'customer_id'    => $subscription->customer_id,
                        'plan_id'        => $subscription->plan_id,
                        'event_type'     => 'expired',
                        'billing_period' => $subscription->billing_period,
                        'amount_paid'    => 0.00,
                    ]);

                    CustomerActivity::create([
                        'customer_id' => $subscription->customer_id,
                        'event_type'  => 'subscription_expired',
                        'description' => 'Billing period ended — subscription moved to expired.',
                        'properties'  => ['subscription_id' => $subscription->id, 'expired_at' => $now->toDateTimeString()],
                    ]);
                });

                // Notify admins
                $admins = User::where('customer_id', $subscription->customer_id)
                    ->whereIn('role', [1, 2])->get();
                if ($admins->isNotEmpty()) {
                    Notification::send($admins, new SubscriptionExpiredNotification($subscription));
                }

                $expired++;
            } catch (Throwable $e) {
                Log::error("Subscription lifecycle: could not expire active sub {$subscription->id}: ".$e->getMessage());
            }
        }

        $log->update([
            'status'       => 'success',
            'completed_at' => now(),
            'output'       => "Expired: {$expired} subscription(s). Warned: {$warned} customer(s).",
        ]);
    } catch (Throwable $e) {
        $log->update(['status' => 'failed', 'completed_at' => now(), 'output' => $e->getMessage()]);
        report($e);
    }
})->dailyAt('00:00')->name('subscription-lifecycle-automation')->withoutOverlapping();

// 4. Stuck-queue detector
//    Runs every 5 minutes. If the pending job count in any relevant queue exceeds the
//    configured threshold (default 100), it fires a QueueStuckNotification to all
//    SuperAdmin users (role=1) so that operations can be alerted quickly.
Schedule::call(function () {
    $threshold = (int) config('queue.stuck_threshold', 100);

    $queues = ['default', 'content', 'publishing', 'notifications'];

    foreach ($queues as $queue) {
        try {
            $pending = DB::table('jobs')
                ->where('queue', $queue)
                ->where('available_at', '<=', now()->timestamp)
                ->count();

            if ($pending >= $threshold) {
                Log::warning("Stuck queue detected: '{$queue}' has {$pending} pending jobs (threshold: {$threshold}).");

                $superAdmins = User::where('role', 1)->get();
                if ($superAdmins->isNotEmpty()) {
                    Notification::send($superAdmins, new QueueStuckNotification($queue, $pending));
                }
            }
        } catch (Throwable $e) {
            Log::error("Stuck queue detector: error checking queue '{$queue}': ".$e->getMessage());
        }
    }
})->everyFiveMinutes()->name('stuck-queue-detector')->withoutOverlapping();

