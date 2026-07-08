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

        // Content Pipeline Interface Bindings with Concrete Services
        $this->app->bind(
            \App\Modules\ContentPipeline\Contracts\TopicResolverInterface::class,
            \App\Modules\TopicManager\Services\TopicResolverService::class
        );

        $this->app->bind(
            \App\Modules\ContentPipeline\Contracts\ResearchServiceInterface::class,
            \App\Modules\ContentPipeline\Services\ResearchService::class
        );

        $this->app->bind(
            \App\Modules\ContentPipeline\Contracts\SourceCollectorInterface::class,
            \App\Modules\ContentPipeline\Services\SourceCollectionService::class
        );

        $this->app->bind(
            \App\Modules\ContentPipeline\Contracts\FactExtractorInterface::class,
            \App\Modules\ContentPipeline\Services\FactExtractionService::class
        );

        $this->app->bind(
            \App\Modules\ContentPipeline\Contracts\ContentGeneratorInterface::class,
            \App\Modules\ContentPipeline\Services\ContentGeneratorService::class
        );

        $this->app->bind(
            \App\Modules\ContentPipeline\Contracts\TranslationInterface::class,
            \App\Modules\ContentPipeline\Services\TranslationService::class
        );

        $this->app->bind(
            \App\Modules\ContentPipeline\Contracts\FactAuditorInterface::class,
            \App\Modules\ContentPipeline\Services\FactAuditService::class
        );

        $this->app->bind(
            \App\Modules\ContentPipeline\Contracts\SEOServiceInterface::class,
            \App\Modules\ContentPipeline\Services\SEOService::class
        );

        $this->app->bind(
            \App\Modules\ContentPipeline\Contracts\MediaPreparatorInterface::class,
            \App\Modules\MediaManager\Services\MediaPreparationService::class
        );

        $this->app->bind(
            \App\Modules\ContentPipeline\Contracts\PublishingQueueInterface::class,
            \App\Modules\Publishing\Services\PublishingQueueService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);
        Gate::policy(\App\Modules\CustomerManager\Models\Workspace::class, \App\Modules\CustomerManager\Policies\WorkspacePolicy::class);
        Gate::policy(\App\Modules\ContentGeneration\Models\GeneratedContent::class, \App\Modules\CustomerManager\Policies\GeneratedContentPolicy::class);


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
