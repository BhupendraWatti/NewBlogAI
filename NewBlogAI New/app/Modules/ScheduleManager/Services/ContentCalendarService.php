<?php

namespace App\Modules\ScheduleManager\Services;

use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\Publishing\Models\PublishingLog;
use App\Modules\ScheduleManager\Models\PublishingSchedule;
use Carbon\Carbon;

class ContentCalendarService
{
    public function __construct(
        protected ScheduleService $scheduleService
    ) {}

    /**
     * Gathers and returns articles in different states and active schedules with predicted runs.
     *
     * @param int $siteId
     * @param string $startRange
     * @param string $endRange
     * @return array
     */
    public function getCalendarEvents(int $siteId, string $startRange, string $endRange): array
    {
        $start = Carbon::parse($startRange);
        $end = Carbon::parse($endRange);

        $events = [];

        // 1. Fetch all GeneratedContent for this site
        $contents = GeneratedContent::where('site_id', $siteId)
            ->with(['topic', 'pipeline'])
            ->get();

        // Fetch publishing logs for this site
        $logs = PublishingLog::where('site_id', $siteId)->get()->groupBy('generated_content_id');

        foreach ($contents as $content) {
            $contentLogs = $logs->get($content->id) ?? collect();

            // Check if there is a pending/processing publishing log with scheduled_at in range
            $scheduledLog = $contentLogs->first(function ($log) use ($start, $end) {
                return $log->scheduled_at &&
                       $log->scheduled_at->between($start, $end) &&
                       in_array($log->status, ['pending', 'processing', 'retrying']);
            });

            if ($scheduledLog) {
                $events[] = [
                    'id' => 'article-' . $content->id . '-scheduled',
                    'type' => 'article',
                    'title' => $content->title,
                    'status' => 'scheduled',
                    'date' => $scheduledLog->scheduled_at->toDateTimeString(),
                    'details' => [
                        'content_id' => $content->id,
                        'topic' => $content->topic?->name,
                        'category' => $content->topic?->category,
                        'pipeline_id' => $content->pipeline_id,
                        'log_id' => $scheduledLog->id,
                    ],
                ];
                continue;
            }

            // Check for Published state
            if ($content->status === 'published') {
                $completedLog = $contentLogs->first(function ($log) {
                    return $log->status === 'completed';
                });
                $pubDate = $completedLog?->completed_at ?: $content->created_at;
                if ($pubDate && $pubDate->between($start, $end)) {
                    $events[] = [
                        'id' => 'article-' . $content->id . '-published',
                        'type' => 'article',
                        'title' => $content->title,
                        'status' => 'published',
                        'date' => $pubDate->toDateTimeString(),
                        'details' => [
                            'content_id' => $content->id,
                            'topic' => $content->topic?->name,
                            'category' => $content->topic?->category,
                            'pipeline_id' => $content->pipeline_id,
                            'published_url' => $completedLog?->published_url,
                        ],
                    ];
                }
                continue;
            }

            // Check for Pending review / Approved state
            if (in_array($content->status, ['pending_review', 'approved'])) {
                if ($content->created_at && $content->created_at->between($start, $end)) {
                    $events[] = [
                        'id' => 'article-' . $content->id . '-' . $content->status,
                        'type' => 'article',
                        'title' => $content->title,
                        'status' => $content->status,
                        'date' => $content->created_at->toDateTimeString(),
                        'details' => [
                            'content_id' => $content->id,
                            'topic' => $content->topic?->name,
                            'category' => $content->topic?->category,
                            'pipeline_id' => $content->pipeline_id,
                        ],
                    ];
                }
            }
        }

        // 2. Fetch all active schedules and predict their run dates
        $schedules = PublishingSchedule::where('site_id', $siteId)
            ->where('is_active', true)
            ->get();

        foreach ($schedules as $schedule) {
            // Event-based schedules don't run on predicted time
            if ($schedule->schedule_mode === 'event_based') {
                continue;
            }

            $currentRun = $schedule->next_run_at;
            if (!$currentRun) {
                // Predict starting from $start
                $currentRun = $this->scheduleService->nextRunAt($schedule->toArray(), $start);
            } else {
                // If it is before the range, fast-forward to start of the range
                if ($currentRun->lt($start)) {
                    while ($currentRun->lt($start)) {
                        $currentRun = $this->scheduleService->nextRunAt($schedule->toArray(), $currentRun);
                    }
                }
            }

            $count = 0;
            while ($currentRun->lte($end) && $count < 100) {
                $events[] = [
                    'id' => 'schedule-' . $schedule->id . '-predicted-' . $currentRun->timestamp,
                    'type' => 'schedule',
                    'title' => $schedule->name,
                    'status' => 'predicted',
                    'date' => $currentRun->toDateTimeString(),
                    'details' => [
                        'schedule_id' => $schedule->id,
                        'frequency' => $schedule->frequency,
                        'timezone' => $schedule->timezone,
                        'time_of_day' => $schedule->time_of_day,
                        'schedule_mode' => $schedule->schedule_mode,
                    ],
                ];

                // Calculate the subsequent run date
                $currentRun = $this->scheduleService->nextRunAt($schedule->toArray(), $currentRun);
                $count++;
            }
        }

        // Sort events chronologically
        usort($events, function ($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        return $events;
    }
}
