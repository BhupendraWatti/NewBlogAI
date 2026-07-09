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
use App\Modules\CustomerManager\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Newsroom Coverage endpoints: start discovery ("News Banao"), list the 9
 * candidates of a discovery run, and select one candidate for full
 * generation. Thin controller — all business logic lives in services.
 *
 * Access requires tenant ownership: platform SuperAdmin/Support bypass,
 * otherwise the user must belong to the owning customer directly or through
 * a workspace employee membership.
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
    public function discover(int $id, Request $request): JsonResponse
    {
        $pipeline = ContentPipeline::with('site')->findOrFail($id);

        if ($denied = $this->deniesAccess($pipeline, $request)) {
            return $denied;
        }

        // Get discovery provider from request body (defaults to 'groq' for fast, free discovery)
        $discoveryProvider = $request->input('discovery_provider', 'groq');

        try {
            $run = $this->pipelineService->triggerDiscovery($pipeline, $discoveryProvider);
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
    public function candidates(int $run, Request $request): JsonResponse
    {
        $pipelineRun = PipelineRun::with(['candidates', 'pipeline.site'])->findOrFail($run);

        if ($denied = $this->deniesAccess($pipelineRun->pipeline, $request)) {
            return $denied;
        }

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
        $candidate = NewsCandidate::with('discoveryRun.pipeline.site')->findOrFail($id);

        if ($denied = $this->deniesAccess($candidate->discoveryRun?->pipeline, $request)) {
            return $denied;
        }

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

    /**
     * Tenant/workspace ownership guard for coverage operations.
     *
     * Returns a JSON error response when access is denied, null when allowed.
     * Platform roles 1 (SuperAdmin) and 3 (Support) bypass — consistent with
     * the customer management route gates. Otherwise the user must belong to
     * the pipeline's owning customer directly (client staff) or through a
     * workspace employee membership.
     */
    protected function deniesAccess(?ContentPipeline $pipeline, Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $pipeline || ! $pipeline->site) {
            return response()->json(['message' => 'Coverage pipeline or its website no longer exists.'], 404);
        }

        if (in_array((int) $user->role, [1, 3], true)) {
            return null;
        }

        $customerId = (int) $pipeline->site->customer_id;

        if ($customerId > 0 && (int) $user->customer_id === $customerId) {
            return null;
        }

        $isWorkspaceEmployee = $customerId > 0 && Employee::where('user_id', $user->id)
            ->whereHas('workspace', fn ($q) => $q->where('customer_id', $customerId))
            ->exists();

        if ($isWorkspaceEmployee) {
            return null;
        }

        return response()->json(['message' => 'You do not have access to this coverage pipeline.'], 403);
    }
}
