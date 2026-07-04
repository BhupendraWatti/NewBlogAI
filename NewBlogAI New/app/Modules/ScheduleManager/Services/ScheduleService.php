<?php

namespace App\Modules\ScheduleManager\Services;

use App\Modules\ContentPipeline\Services\PipelineService;
use App\Modules\ScheduleManager\Models\PublishingSchedule;
use App\Modules\SiteManager\Models\Site;
use App\Modules\SubscriptionManager\Services\EntitlementService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ScheduleService
{
    public function __construct(
        protected EntitlementService $entitlements,
        protected PipelineService $pipelines,
    ) {}

    public function create(array $data): PublishingSchedule
    {
        $site = Site::findOrFail($data['site_id']);
        $this->assertValidConfiguration($site, $data);
        $this->entitlements->assertCanCreateSchedule($site);

        $data['timezone'] ??= $site->timezone ?? 'UTC';
        $data['next_run_at'] = ! empty($data['is_active'])
            ? $this->nextRunAt($data)
            : null;

        return PublishingSchedule::create($data)->refresh();
    }

    public function update(PublishingSchedule $schedule, array $data): PublishingSchedule
    {
        $site = isset($data['site_id'])
            ? Site::findOrFail($data['site_id'])
            : $schedule->site;
        $merged = array_merge($schedule->toArray(), $data);

        $this->assertValidConfiguration($site, $merged);
        $this->entitlements->assertCanCreateSchedule($site, $schedule->id);

        $merged['next_run_at'] = ! empty($merged['is_active'])
            ? $this->nextRunAt($merged)
            : null;

        $schedule->update(array_intersect_key($merged, array_flip($schedule->getFillable())));

        return $schedule->refresh();
    }

    public function delete(PublishingSchedule $schedule): void
    {
        $schedule->delete();
    }

    public function runDue(): int
    {
        $processed = 0;

        PublishingSchedule::query()
            ->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->orderBy('id')
            ->each(function (PublishingSchedule $schedule) use (&$processed): void {
                DB::transaction(function () use ($schedule, &$processed): void {
                    $locked = PublishingSchedule::query()
                        ->with(['site', 'pipeline'])
                        ->lockForUpdate()
                        ->find($schedule->id);

                    if (! $locked?->is_active || ! $locked->next_run_at?->lte(now())) {
                        return;
                    }

                    if (! $locked->pipeline || $locked->pipeline->site_id !== $locked->site_id) {
                        throw new InvalidArgumentException('A schedule must reference a pipeline owned by the same website.');
                    }

                    $this->entitlements->assertCanGenerate($locked->site);
                    $this->pipelines->triggerRun($locked->pipeline);

                    $locked->update([
                        'last_run_at' => now(),
                        'next_run_at' => $this->nextRunAt($locked->toArray(), now()),
                    ]);
                    $processed++;
                });
            });

        return $processed;
    }

    public function nextRunAt(array $configuration, mixed $after = null): CarbonImmutable
    {
        $timezone = $configuration['timezone'] ?? 'UTC';
        $frequency = $configuration['frequency'] ?? 'daily';
        $timeOfDay = substr((string) ($configuration['time_of_day'] ?? '09:00:00'), 0, 8);
        $reference = CarbonImmutable::parse($after ?? 'now', 'UTC')->setTimezone($timezone);

        if ($frequency === 'hourly') {
            return $reference->addHour()->startOfHour()->utc();
        }

        if ($frequency === 'twice_daily') {
            $hours = [9, 17];
            foreach ($hours as $hour) {
                $candidate = $reference->setTime($hour, 0);
                if ($candidate->isAfter($reference)) {
                    return $candidate->utc();
                }
            }

            return $reference->addDay()->setTime($hours[0], 0)->utc();
        }

        $candidate = CarbonImmutable::parse($reference->toDateString().' '.$timeOfDay, $timezone);
        if (! $candidate->isAfter($reference)) {
            $candidate = match ($frequency) {
                'weekly' => $candidate->addWeek(),
                'monthly' => $candidate->addMonthNoOverflow(),
                default => $candidate->addDay(),
            };
        }

        if ($frequency === 'weekly' && ! empty($configuration['days_of_week'])) {
            $allowedDays = array_map('strtolower', $configuration['days_of_week']);
            while (! in_array(strtolower($candidate->englishDayOfWeek), $allowedDays, true)) {
                $candidate = $candidate->addDay();
            }
        }

        return $candidate->utc();
    }

    private function assertValidConfiguration(Site $site, array $data): void
    {
        if (! $site->is_active) {
            throw new InvalidArgumentException('Schedules cannot be assigned to an inactive website.');
        }

        $this->entitlements->assertFrequencyAllowed($site, $data['frequency'] ?? 'daily');

        if (! empty($data['pipeline_id'])) {
            $pipelineBelongsToSite = $site->pipelines()->whereKey($data['pipeline_id'])->exists();
            if (! $pipelineBelongsToSite) {
                throw new InvalidArgumentException('The selected pipeline does not belong to this website.');
            }
        }
    }
}
