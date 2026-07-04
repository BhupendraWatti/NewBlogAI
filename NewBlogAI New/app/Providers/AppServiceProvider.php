<?php

namespace App\Providers;

use App\Modules\CustomerManager\Models\Customer;
use App\Modules\CustomerManager\Policies\CustomerPolicy;
use App\Modules\Operations\Models\JobLog;
use App\Modules\SubscriptionManager\Contracts\PaymentGatewayInterface;
use App\Modules\SubscriptionManager\Models\Subscription;
use App\Modules\SubscriptionManager\Policies\SubscriptionPolicy;
use App\Modules\SubscriptionManager\Services\PaymentGatewayStub;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, PaymentGatewayStub::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);

        // Queue Event Listeners for Operational Monitoring
        Queue::before(function (JobProcessing $event) {
            $jobId = $event->job->getJobId() ?: $event->job->resolveName().':'.$event->job->getQueue();
            JobLog::updateOrCreate(
                ['job_id' => $jobId],
                [
                    'name' => $event->job->resolveName(),
                    'queue' => $event->job->getQueue(),
                    'status' => 'processing',
                    'attempts' => $event->job->attempts(),
                    'started_at' => now(),
                ]
            );
        });

        Queue::after(function (JobProcessed $event) {
            $jobId = $event->job->getJobId() ?: $event->job->resolveName().':'.$event->job->getQueue();
            JobLog::updateOrCreate(
                ['job_id' => $jobId],
                [
                    'status' => 'completed',
                    'completed_at' => now(),
                ]
            );
        });

        Queue::failing(function (JobFailed $event) {
            $jobId = $event->job->getJobId() ?: $event->job->resolveName().':'.$event->job->getQueue();
            JobLog::updateOrCreate(
                ['job_id' => $jobId],
                [
                    'status' => 'failed',
                    'exception' => $event->exception->getMessage(),
                    'completed_at' => now(),
                ]
            );
        });
    }
}
