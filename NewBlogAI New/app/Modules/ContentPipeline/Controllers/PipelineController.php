<?php

namespace App\Modules\ContentPipeline\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\ContentPipeline\Requests\StorePipelineRequest;
use App\Modules\ContentPipeline\Requests\UpdatePipelineRequest;
use App\Modules\ContentPipeline\Resources\PipelineResource;
use App\Modules\ContentPipeline\Resources\PipelineRunResource;
use App\Modules\ContentPipeline\Services\PipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PipelineController extends Controller
{
    public function __construct(
        protected PipelineService $pipelineService
    ) {}

    /**
     * Display a listing of content pipelines.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['site_id', 'status']);
        $limit = $request->input('limit', 15);

        if (\Illuminate\Support\Facades\Auth::user()->role !== 1) {
            $filters['customer_id'] = \Illuminate\Support\Facades\Auth::user()->customer_id;
        }

        $pipelines = $this->pipelineService->getPaginated($filters, $limit);

        return PipelineResource::collection($pipelines);
    }

    /**
     * Store a newly created content pipeline configuration.
     */
    public function store(StorePipelineRequest $request): JsonResponse
    {
        if (\Illuminate\Support\Facades\Auth::user()->role !== 1) {
            $site = \App\Modules\SiteManager\Models\Site::findOrFail($request->input('site_id'));
            if ($site->customer_id !== \Illuminate\Support\Facades\Auth::user()->customer_id) {
                abort(403, 'Unauthorized site selection.');
            }
        }

        $pipeline = $this->pipelineService->createPipeline($request->validated());

        return (new PipelineResource($pipeline->load(['site', 'prompt', 'provider'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified content pipeline configuration.
     */
    public function show(string $id): PipelineResource
    {
        $pipeline = $this->findPipelineOrFail($id, ['site', 'prompt', 'provider', 'runs']);

        return new PipelineResource($pipeline);
    }

    /**
     * Update the specified content pipeline configuration.
     */
    public function update(UpdatePipelineRequest $request, string $id): PipelineResource
    {
        $pipeline = $this->findPipelineOrFail($id);
        $validated = $request->validated();

        if (isset($validated['site_id']) && \Illuminate\Support\Facades\Auth::user()->role !== 1) {
            $site = \App\Modules\SiteManager\Models\Site::findOrFail($validated['site_id']);
            if ($site->customer_id !== \Illuminate\Support\Facades\Auth::user()->customer_id) {
                abort(403, 'Unauthorized site selection.');
            }
        }

        $updated = $this->pipelineService->updatePipeline($pipeline, $validated);

        return new PipelineResource($updated->load(['site', 'prompt', 'provider']));
    }

    /**
     * Remove the specified content pipeline configuration.
     */
    public function destroy(string $id): JsonResponse
    {
        $pipeline = $this->findPipelineOrFail($id);
        $pipeline->delete();

        return response()->json([
            'message' => 'Content pipeline config deleted successfully.',
        ]);
    }

    /**
     * Trigger execution run for a pipeline.
     */
    public function execute(string $id): JsonResponse
    {
        $pipeline = $this->findPipelineOrFail($id);
        $run = $this->pipelineService->triggerRun($pipeline);

        return response()->json([
            'message' => 'Content generation run queued successfully.',
            'run' => new PipelineRunResource($run),
        ], 202);
    }

    /**
     * Retry a failed pipeline execution run.
     */
    public function retry(string $runId): JsonResponse
    {
        $run = $this->findRunOrFail($runId, ['pipeline']);
        $newRun = $this->pipelineService->retryRun($run);

        return response()->json([
            'message' => 'Retry run queued successfully.',
            'run' => new PipelineRunResource($newRun),
        ], 202);
    }

    /**
     * Cancel an active pipeline execution run.
     */
    public function cancel(string $runId): JsonResponse
    {
        $run = $this->findRunOrFail($runId, ['pipeline']);
        $this->pipelineService->cancelRun($run);

        return response()->json([
            'message' => 'Pipeline execution run cancelled successfully.',
        ]);
    }

    /**
     * List history of runs for a specific pipeline.
     */
    public function history(string $id): AnonymousResourceCollection
    {
        $pipeline = $this->findPipelineOrFail($id);
        $runs = PipelineRun::where('pipeline_id', $pipeline->id)->latest('id')->paginate(15);

        return PipelineRunResource::collection($runs);
    }

    /**
     * Find a pipeline by ID while enforcing tenant isolation.
     */
    private function findPipelineOrFail(string $id, array $relations = []): ContentPipeline
    {
        $query = ContentPipeline::query();
        if (! empty($relations)) {
            $query->with($relations);
        }

        if (\Illuminate\Support\Facades\Auth::user()->role !== 1) {
            $query->whereHas('site', function ($q) {
                $q->where('customer_id', \Illuminate\Support\Facades\Auth::user()->customer_id);
            });
        }

        return $query->findOrFail($id);
    }

    /**
     * Find a run by ID while enforcing tenant isolation.
     */
    private function findRunOrFail(string $runId, array $relations = []): PipelineRun
    {
        $query = PipelineRun::query();
        if (! empty($relations)) {
            $query->with($relations);
        }

        if (\Illuminate\Support\Facades\Auth::user()->role !== 1) {
            $query->whereHas('pipeline.site', function ($q) {
                $q->where('customer_id', \Illuminate\Support\Facades\Auth::user()->customer_id);
            });
        }

        return $query->findOrFail($runId);
    }
}
