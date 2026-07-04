<?php

namespace App\Modules\SubscriptionManager\Services;

use App\Modules\ContentGeneration\Models\AIRequestLog;
use App\Modules\CustomerManager\Models\Customer;
use App\Modules\Publishing\Models\PublishingLog;
use App\Modules\ScheduleManager\Models\PublishingSchedule;
use App\Modules\SiteManager\Models\Site;
use App\Modules\SubscriptionManager\Exceptions\EntitlementDeniedException;
use App\Modules\SubscriptionManager\Models\Subscription;
use App\Modules\TopicManager\Models\Topic;
use App\Modules\PromptManager\Models\Prompt;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EntitlementService
{
    private const ACTIVE_STATUSES = ['active', 'trial'];

    private const FREQUENCY_WEIGHT = [
        'hourly' => 24,
        'twice_daily' => 2,
        'daily' => 1,
        'weekly' => 0.14,
        'monthly' => 0.03,
    ];

    public function activeSubscription(Customer|string $customer): Subscription
    {
        $customerId = $customer instanceof Customer ? $customer->id : $customer;

        $subscription = Subscription::query()
            ->with('plan')
            ->where('customer_id', $customerId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->first();

        if (!$subscription || !$subscription->plan || $subscription->plan->status !== 'active') {
            throw new EntitlementDeniedException(
                'An active subscription is required for this operation.',
                'active_subscription',
            );
        }

        if ($subscription->starts_at?->isFuture()
            || ($subscription->ends_at && $subscription->ends_at->isPast())
            || ($subscription->status === 'trial' && $subscription->trial_ends_at?->isPast())) {
            throw new EntitlementDeniedException(
                'The subscription is outside its active billing period.',
                'active_subscription',
            );
        }

        return $subscription;
    }

    public function limits(Subscription $subscription): array
    {
        $subscription->loadMissing('plan');
        $plan = $subscription->plan;
        $snapshot = $subscription->limits ?? [];

        return [
            'max_wordpress_sites' => (int) Arr::get($snapshot, 'max_wordpress_sites', $plan->max_wordpress_sites),
            'max_topics' => (int) Arr::get($snapshot, 'max_topics', $plan->max_topics),
            'publishing_schedule_limit' => (int) Arr::get($snapshot, 'publishing_schedule_limit', $plan->publishing_schedule_limit),
            'max_articles_per_day' => (int) Arr::get($snapshot, 'max_articles_per_day', $plan->max_articles_per_day),
            'monthly_generation_limit' => (int) Arr::get($snapshot, 'monthly_generation_limit', $plan->monthly_generation_limit),
            'prompt_templates_allowed' => (int) Arr::get($snapshot, 'prompt_templates_allowed', $plan->prompt_templates_allowed),
            'ai_providers_available' => array_values(Arr::get($snapshot, 'ai_providers_available', $plan->ai_providers_available ?? [])),
            'api_keys_allowed' => (int) Arr::get($snapshot, 'api_keys_allowed', $plan->api_keys_allowed),
            'storage_limit' => (int) Arr::get($snapshot, 'storage_limit', $plan->storage_limit),
            'minimum_publishing_frequency' => (string) Arr::get(
                $snapshot,
                'minimum_publishing_frequency',
                $plan->minimum_publishing_frequency ?? 'daily',
            ),
            'feature_flags' => array_merge(
                $plan->feature_flags ?? [],
                Arr::get($snapshot, 'feature_flags', []),
            ),
            'analytics_access' => (bool) Arr::get($snapshot, 'analytics_access', $plan->analytics_access),
            'priority_support' => (bool) Arr::get($snapshot, 'priority_support', $plan->priority_support),
        ];
    }

    public function usage(Subscription $subscription): array
    {
        $customerId = $subscription->customer_id;
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $dayStart = now()->startOfDay();
        $siteIds = Site::where('customer_id', $customerId)->pluck('id');

        return [
            'websites' => $siteIds->count(),
            'topics' => Topic::where('subscription_id', $subscription->id)->count(),
            'schedules' => PublishingSchedule::whereIn('site_id', $siteIds)->count(),
            'monthly_generations' => AIRequestLog::where('subscription_id', $subscription->id)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count(),
            'articles_today' => PublishingLog::whereIn('site_id', $siteIds)
                ->where('created_at', '>=', $dayStart)
                ->whereNotIn('status', ['failed', 'cancelled'])
                ->count(),
        ];
    }

    public function assertCanRegisterSite(Customer $customer, ?int $excludingSiteId = null): Subscription
    {
        $subscription = $this->activeSubscription($customer);
        $limit = $this->limits($subscription)['max_wordpress_sites'];
        $usage = Site::where('customer_id', $customer->id)
            ->when($excludingSiteId, fn ($query) => $query->whereKeyNot($excludingSiteId))
            ->count();

        $this->assertBelowLimit('max_wordpress_sites', $usage, $limit);

        return $subscription;
    }

    public function assertCanCreateTopic(Subscription $subscription): void
    {
        $limit = $this->limits($subscription)['max_topics'];
        $usage = Topic::where('subscription_id', $subscription->id)->count();

        $this->assertBelowLimit('max_topics', $usage, $limit);
    }

    public function assertCanCreatePrompt(Topic $topic): void
    {
        if (!$topic->subscription_id) {
            return;
        }

        $ownedSubscription = Subscription::with('plan')->findOrFail($topic->subscription_id);
        $subscription = $this->activeSubscription($ownedSubscription->customer_id);
        if ($subscription->id !== $ownedSubscription->id) {
            throw new EntitlementDeniedException(
                'The topic is not owned by the active subscription.',
                'topic_subscription',
            );
        }

        $limit = $this->limits($subscription)['prompt_templates_allowed'];
        $topicIds = Topic::where('subscription_id', $subscription->id)->pluck('id');
        $usage = Prompt::whereIn('topic_id', $topicIds)->count();

        $this->assertBelowLimit('prompt_templates_allowed', $usage, $limit);
    }

    public function assertProviderAvailable(Site $site, string $providerKey): void
    {
        $subscription = $this->subscriptionForSite($site);
        if (!$subscription) {
            return;
        }

        $available = array_map('strtolower', $this->limits($subscription)['ai_providers_available']);
        if ($available !== [] && !in_array(strtolower($providerKey), $available, true)) {
            throw new EntitlementDeniedException(
                "The {$providerKey} provider is not available on this subscription.",
                'ai_providers_available',
                implode(',', $available),
                strtolower($providerKey),
            );
        }
    }

    public function assertCanGenerate(Site $site): ?Subscription
    {
        $subscription = $this->subscriptionForSite($site);
        if (!$subscription) {
            return null;
        }

        $limit = $this->limits($subscription)['monthly_generation_limit'];
        $usage = AIRequestLog::where('subscription_id', $subscription->id)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        $this->assertBelowLimit('monthly_generation_limit', $usage, $limit);

        return $subscription;
    }

    public function reserveGeneration(
        Site $site,
        string $provider,
        string $model,
        ?int $promptId,
        ?int $topicId,
    ): ?AIRequestLog {
        $subscription = $this->subscriptionForSite($site);
        if (!$subscription) {
            return null;
        }

        return DB::transaction(function () use ($site, $subscription, $provider, $model, $promptId, $topicId) {
            $locked = Subscription::query()
                ->with('plan')
                ->lockForUpdate()
                ->findOrFail($subscription->id);
            $limit = $this->limits($locked)['monthly_generation_limit'];
            $usage = AIRequestLog::where('subscription_id', $locked->id)
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count();

            $this->assertBelowLimit('monthly_generation_limit', $usage, $limit);

            return AIRequestLog::create([
                'customer_id' => $site->customer_id,
                'subscription_id' => $locked->id,
                'site_id' => $site->id,
                'provider' => $provider,
                'model' => $model,
                'prompt_id' => $promptId,
                'topic_id' => $topicId,
                'execution_time_ms' => 0,
                'status' => 'pending',
            ]);
        });
    }

    public function assertCanPublish(Site $site): ?Subscription
    {
        $subscription = $this->subscriptionForSite($site);
        if (!$subscription) {
            return null;
        }

        $limit = $this->limits($subscription)['max_articles_per_day'];
        $siteIds = Site::where('customer_id', $site->customer_id)->pluck('id');
        $usage = PublishingLog::whereIn('site_id', $siteIds)
            ->where('created_at', '>=', now()->startOfDay())
            ->whereNotIn('status', ['failed', 'cancelled'])
            ->count();

        $this->assertBelowLimit('max_articles_per_day', $usage, $limit);

        return $subscription;
    }

    public function assertCanCreateSchedule(Site $site, ?int $excludingScheduleId = null): ?Subscription
    {
        $subscription = $this->subscriptionForSite($site);
        if (!$subscription) {
            return null;
        }

        $limit = $this->limits($subscription)['publishing_schedule_limit'];
        $siteIds = Site::where('customer_id', $site->customer_id)->pluck('id');
        $usage = PublishingSchedule::whereIn('site_id', $siteIds)
            ->when($excludingScheduleId, fn ($query) => $query->whereKeyNot($excludingScheduleId))
            ->count();

        $this->assertBelowLimit('publishing_schedule_limit', $usage, $limit);

        return $subscription;
    }

    public function assertFrequencyAllowed(Site $site, string $frequency): void
    {
        $subscription = $this->subscriptionForSite($site);
        if (!$subscription) {
            return;
        }

        $minimum = $this->limits($subscription)['minimum_publishing_frequency'];
        $requestedWeight = self::FREQUENCY_WEIGHT[$frequency] ?? 0;
        $allowedWeight = self::FREQUENCY_WEIGHT[$minimum] ?? 1;

        if ($requestedWeight > $allowedWeight) {
            throw new EntitlementDeniedException(
                "The {$frequency} publishing frequency is not available on this subscription.",
                'minimum_publishing_frequency',
                $minimum,
                $frequency,
            );
        }
    }

    public function assertFeatureEnabled(Customer $customer, string $feature): void
    {
        $subscription = $this->activeSubscription($customer);
        $limits = $this->limits($subscription);
        $enabled = match ($feature) {
            'analytics' => $limits['analytics_access'],
            'priority_support' => $limits['priority_support'],
            default => (bool) ($limits['feature_flags'][$feature] ?? false),
        };

        if (!$enabled) {
            throw new EntitlementDeniedException(
                "The {$feature} feature is not enabled for this subscription.",
                "feature_flags.{$feature}",
            );
        }
    }

    public function subscriptionForSite(Site $site): ?Subscription
    {
        if (!$site->customer_id) {
            return null;
        }

        return $this->activeSubscription($site->customer_id);
    }

    private function assertBelowLimit(string $entitlement, int $usage, int $limit): void
    {
        if ($limit >= 0 && $usage >= $limit) {
            throw new EntitlementDeniedException(
                "The subscription limit for {$entitlement} has been reached.",
                $entitlement,
                $limit,
                $usage,
            );
        }
    }
}
