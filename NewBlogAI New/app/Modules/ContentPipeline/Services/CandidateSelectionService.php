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
 * Exactly one candidate per coverage run may be selected. All guards run
 * inside a single transaction with pessimistic row locks on the candidate
 * and its discovery run, so concurrent selections by multiple employees
 * are serialized and cannot both pass the one-selection-per-run guard.
 *
 * Selection re-checks uniqueness (history may have changed since
 * discovery), then triggers a standard full generation run through
 * PipelineService so the selected event flows through the existing
 * 10-stage pipeline, approval queue, scheduler, and publishing engine
 * unchanged.
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
        $result = DB::transaction(function () use ($candidate, $userId) {
            // Serialize concurrent selections: lock the candidate row and its
            // discovery run row for the duration of the guards and updates.
            $lockedCandidate = NewsCandidate::whereKey($candidate->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedCandidate) {
                throw new InvalidArgumentException('Candidate no longer exists.');
            }

            $discoveryRun = PipelineRun::whereKey($lockedCandidate->pipeline_run_id)
                ->lockForUpdate()
                ->first();

            if (! $discoveryRun || ! $discoveryRun->isDiscovery()) {
                throw new InvalidArgumentException('Candidate does not belong to a coverage discovery run.');
            }

            if ($discoveryRun->status !== PipelineRun::STATUS_READY) {
                throw new InvalidArgumentException('Coverage run is not awaiting selection (status: '.$discoveryRun->status.').');
            }

            if (! $lockedCandidate->isSelectable()) {
                throw new InvalidArgumentException("Candidate is not selectable (status: {$lockedCandidate->status}).");
            }

            $alreadySelected = NewsCandidate::where('pipeline_run_id', $discoveryRun->id)
                ->where('status', NewsCandidate::STATUS_SELECTED)
                ->lockForUpdate()
                ->exists();

            if ($alreadySelected) {
                throw new InvalidArgumentException('A candidate has already been selected for this coverage run.');
            }

            $pipeline = $discoveryRun->pipeline()->with('site')->first();
            if (! $pipeline || ! $pipeline->site) {
                throw new InvalidArgumentException('Coverage run pipeline or site no longer exists.');
            }

            // Duplicate re-check at selection time: content may have been
            // published between discovery and this selection. The duplicate
            // marking must survive the rejection, so it is committed here and
            // the exception is thrown after the transaction completes.
            if ($this->duplicates->isDuplicate($lockedCandidate->title, (array) ($lockedCandidate->keywords ?? []), $pipeline->site_id)) {
                $lockedCandidate->update(['status' => NewsCandidate::STATUS_DUPLICATE]);

                return ['duplicate' => true, 'run' => null];
            }

            $fullRun = $this->pipelines->triggerRun($pipeline, [
                'selected_candidate' => [
                    'news_candidate_id' => $lockedCandidate->id,
                    'title' => $lockedCandidate->title,
                    'summary' => $lockedCandidate->summary,
                    'keywords' => $lockedCandidate->keywords,
                    'source_references' => $lockedCandidate->source_references,
                ],
            ]);

            $lockedCandidate->update([
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
                'candidate_id' => $lockedCandidate->id,
                'discovery_run_id' => $discoveryRun->id,
                'full_run_id' => $fullRun->id,
                'selected_by' => $userId,
            ]);

            return ['duplicate' => false, 'run' => $fullRun];
        });

        if ($result['duplicate']) {
            throw new InvalidArgumentException(
                'Selected candidate duplicates recently published news. Please choose another candidate.'
            );
        }

        return $result['run'];
    }
}
