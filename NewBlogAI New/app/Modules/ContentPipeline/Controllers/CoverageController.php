<?php

namespace App\Modules\ContentPipeline\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\ContentPipeline\Models\NewsCandidate;
use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\ContentPipeline\Resources\NewsCandidateResource;
use App\Modules\ContentPipeline\Resources\PipelineRunResource;
use App\Modules\ContentPipeline\Services\CandidateSelectionService;
use App\Modules\ContentPipeline\Services\PipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Newsroom Coverage endpoints: start discovery ("News Banao"), list the 9
 * candidates of a discovery run, and select one candidate for full
 * generation. Thin controller — all business logic lives in services.
 */
class CoverageController extends Controller
{
    public function __construct(
        protected PipelineService $pipelineService,
        protected CandidateSelectionService $selectionService,
    ) {}

    /**
     * POST /api/v1/pipelines/{id}/discover
     * Start a coverage discovery run producing 9 news candidates.
     */
    public function discover(int $id): JsonResponse
    {
        $pipeline = ContentPipeline::findOrFail($id);

        try {
            $run = $this->pipelineService->triggerDiscovery($pipeline);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Coverage discovery queued. Candidates will be ready for selection shortly.',
            'data' => new PipelineRunResource($run),
        ], 202);
    }

    /**
     * GET /api/v1/pipelines/runs/{run}/candidates
     * List the news candidates of a discovery run.
     */
    public function candidates(int $run): JsonResponse
    {
        $pipelineRun = PipelineRun::with('candidates')->findOrFail($run);

        if (! $pipelineRun->isDiscovery()) {
            return response()->json(['message' => 'Run is not a coverage discovery run.'], 422);
        }

        return response()->json([
            'data' => [
                'run' => new PipelineRunResource($pipelineRun),
                'candidates' => NewsCandidateResource::collection($pipelineRun->candidates),
            ],
        ]);
    }

    /**
     * POST /api/v1/coverage/candidates/{id}/select
     * Employee selects one candidate; full generation is queued for it only.
     */
    public function select(int $id, Request $request): JsonResponse
    {
        $candidate = NewsCandidate::findOrFail($id);

        try {
            $fullRun = $this->selectionService->select($candidate, $request->user()?->id);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Candidate selected. Full news generation queued.',
            'data' => [
                'candidate' => new NewsCandidateResource($candidate->fresh()),
                'full_run' => new PipelineRunResource($fullRun),
            ],
        ], 202);
    }
}
