<?php

namespace App\Modules\SiteManager\Services;

use App\Modules\SiteManager\Models\Site;
use App\Modules\SubscriptionManager\Exceptions\EntitlementDeniedException;
use App\Modules\SubscriptionManager\Services\EntitlementService;
use Illuminate\Support\Arr;

class SiteConfigurationService
{
    public function __construct(protected EntitlementService $entitlements) {}

    public function build(Site $site): array
    {
        $site->loadMissing([
            'customer.subscription.plan',
            'pipelines.prompt',
            'pipelines.provider',
            'schedules',
        ]);

        $subscription = $site->customer?->subscription;
        $executionEnabled = false;
        $limits = [];
        $usage = [];

        if ($subscription?->plan) {
            $limits = $this->entitlements->limits($subscription);
            $usage = $this->entitlements->usage($subscription);

            try {
                $this->entitlements->activeSubscription($site->customer);
                $executionEnabled = $site->is_active && $site->status !== 'disconnected';
            } catch (EntitlementDeniedException) {
                $executionEnabled = false;
            }
        }

        $pipelines = $site->pipelines
            ->filter(fn ($pipeline) => $pipeline->is_active)
            ->map(fn ($pipeline): array => [
                'id'           => $pipeline->id,
                'news_category' => $pipeline->news_category ?? 'global',
                'language'     => $pipeline->language ?: 'en',
                'prompt_id'    => $pipeline->prompt_id,
                'pipeline_id'  => $pipeline->id,
                'provider'     => $pipeline->provider?->provider_key,
                'model'        => $pipeline->provider?->default_model,
            ])
            ->values()
            ->all();

        if ($pipelines === []) {
            $pipelines = [];
        }

        $schedules = $site->schedules
            ->where('is_active', true)
            ->map(fn ($schedule): array => [
                'id' => $schedule->id,
                'pipeline_id' => $schedule->pipeline_id,
                'name' => $schedule->name,
                'frequency' => $schedule->frequency,
                'timezone' => $schedule->timezone,
                'time_of_day' => $schedule->time_of_day,
                'days_of_week' => $schedule->days_of_week ?? [],
                'next_run_at' => $schedule->next_run_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        if ($schedules === [] && $site->slot) {
            $schedules[] = [
                'id' => null,
                'pipeline_id' => null,
                'name' => 'Legacy schedule',
                'frequency' => strtolower($site->slot),
                'timezone' => $site->timezone ?? 'UTC',
                'time_of_day' => null,
                'days_of_week' => [],
                'next_run_at' => null,
            ];
        }

        $configuration = [
            'schema_version' => '1.0',
            'generated_at' => now()->toIso8601String(),
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
                'domain_url' => $site->domain_url,
                'active' => (bool) $site->is_active,
                'connection_status' => $site->status,
                'timezone' => $site->timezone ?? 'UTC',
            ],
            'subscription' => [
                'id' => $subscription?->id,
                'status' => $subscription?->status ?? 'unassigned',
                'plan' => $subscription?->plan?->name,
                'execution_enabled' => $executionEnabled,
                'limits' => $limits,
                'usage' => $usage,
                'feature_flags' => Arr::get($limits, 'feature_flags', []),
            ],
            'content' => [
                'pipelines'       => $pipelines,
                'category_mapping' => $site->category_mapping ?? [],
            ],
            'scheduling' => [
                'owned_by' => 'laravel',
                'schedules' => $schedules,
            ],
            'publishing' => [
                'mode' => $site->publishing_mode ?? 'draft',
                'enabled' => $executionEnabled,
            ],
            'synchronization' => array_merge([
                'configuration_version' => (int) $site->configuration_version,
                'last_synced_at' => $site->last_synced_at?->toIso8601String(),
                'heartbeat_interval_seconds' => 3600,
                'configuration_refresh_seconds' => 43200,
            ], $site->sync_settings ?? []),
        ];

        $configuration['configuration_hash'] = hash(
            'sha256',
            json_encode($configuration, JSON_THROW_ON_ERROR),
        );

        // Compatibility keys consumed by existing plugin versions.
        $configuration['site_id'] = $site->id;
        $configuration['slot'] = $site->slot ?? ($schedules[0]['frequency'] ?? 'daily');
        $configuration['selected_categories'] = array_values(array_map(
            fn (array $p): string => $p['news_category'],
            $pipelines,
        ));
        // Backward-compat: keep selected_topics as an alias
        $configuration['selected_topics'] = $configuration['selected_categories'];
        $configuration['is_active'] = (bool) $site->is_active;

        return $configuration;
    }
}
