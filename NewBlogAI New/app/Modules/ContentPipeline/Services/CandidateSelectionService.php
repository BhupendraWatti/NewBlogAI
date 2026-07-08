<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Services;

use App\Modules\ContentPipeline\Models\NewsCandidate;
use App\Modules\ContentPipeline\Models\PipelineRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Employee selection gate of the newsroom workflow.
 *
 * Exactly one candidate per coverage run may be selected. Selection
 * re-checks uniqueness (history may have changed since discovery), then
 * triggers a standard full generation run through PipelineService so the
 * selected event flows through the existing 10-stage pipeline, approval
 * queue, scheduler, and publishing engine unchanged.
 */
class CandidateSelectionService
{
    public function __construct(
        protected PipelineService $pipelines,
        protected DuplicateDetectionService $duplicates,
    ) {}

    /**
     * Select a candidate and trigger full generation for it.
     *
     * @param int|null $userId the employee performing the selection
     * @return PipelineRun the full generation run
     *
     * @throws InvalidArgumentException when selection guards fail
     */
    public function select(NewsCandidate $candidate, ?int $userId = null): PipelineRun
    {
        $discoveryRun = $candidate->discoveryRun()->with('pipeline.site')->first();

        if (! $discoveryRun || ! $discoveryRun->isDiscovery()) {
            throw new InvalidArgumentException('Candidate does not belong to a coverage discovery run.');
        }

        if ($discoveryRun->status !== PipelineRun::STATUS_READY) {
            throw new InvalidArgumentException('Coverage run is not awaiting selection (status: '.$discoveryRun->status.').');
        }

        if (! $candidate->isSelectable()) {
            throw new InvalidArgumentException("Candidate is not selectable (status: {$candidate->status}).");
        }

        $alreadySelected = NewsCandidate::where('pipeline_run_id', $discoveryRun->id)
            ->where('status', NewsCandidate::STATUS_SELECTED)
            ->exists();

        if ($alreadySelected) {
            throw new InvalidArgumentException('A candidate has already been selected for this coverage run.');
        }

        $pipeline = $discoveryRun->pipeline;
        if (! $pipeline || ! $pipeline->site) {
            throw new InvalidArgumentException('Coverage run pipeline or site no longer exists.');
        }

        // Duplicate re-check at selection time: content may have been
        // published between discovery and this selection.
        if ($this->duplicates->isDuplicate($candidate->title, (array) ($candidate->keywords ?? []), $pipeline->site_id)) {
            $candidate->update(['status' => NewsCandidate::STATUS_DUPLICATE]);

            throw new InvalidArgumentException(
                'Selected candidate duplicates recently published news. Please choose another candidate.'
            );
        }

        return DB::transaction(function () use ($candidate, $discoveryRun, $pipeline, $userId) {
            $fullRun = $this->pipelines->triggerRun($pipeline, [
                'selected_candidate' => [
                    'news_candidate_id' => $candidate->id,
                    'title' => $candidate->title,
                    'summary' => $candidate->summary,
                    'keywords' => $candidate->keywords,
                    'source_references' => $candidate->source_references,
                ],
            ]);

            $candidate->update([
                'status' => NewsCandidate::STATUS_SELECTED,
                'selected_by' => $userId,
                'selected_at' => now(),
                'full_run_id' => $fullRun->id,
            ]);

            $discoveryRun->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            Log::info('Coverage candidate selected; full generation queued.', [
                'candidate_id' => $candidate->id,
                'discovery_run_id' => $discoveryRun->id,
                'full_run_id' => $fullRun->id,
                'selected_by' => $userId,
            ]);

            return $fullRun;
        });
    }
}
