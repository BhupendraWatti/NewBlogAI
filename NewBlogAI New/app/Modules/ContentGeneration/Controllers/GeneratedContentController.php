<?php

namespace App\Modules\ContentGeneration\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ContentGeneration\Models\AIRequestLog;
use App\Modules\ContentGeneration\Models\ContentRevision;
use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\ContentGeneration\Requests\UpdateGeneratedContentRequest;
use App\Modules\ContentGeneration\Requests\UpdateStatusRequest;
use App\Modules\ContentGeneration\Resources\AIRequestLogResource;
use App\Modules\ContentGeneration\Resources\ContentRevisionResource;
use App\Modules\ContentGeneration\Resources\GeneratedContentResource;
use App\Modules\ContentGeneration\Services\ContentGenerationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class GeneratedContentController extends Controller
{
    public function __construct(
        protected ContentGenerationService $generationService
    ) {}

    /**
     * Display a listing of generated articles.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['status', 'search']);
        $limit = $request->input('limit', 15);

        if (Auth::user()->role !== 1) {
            $filters['customer_id'] = Auth::user()->customer_id;
        }

        $articles = $this->generationService->getPaginated($filters, $limit);

        return GeneratedContentResource::collection($articles);
    }

    /**
     * Display the specified generated content.
     */
    public function show(string $id): GeneratedContentResource
    {
        $content = $this->findContentOrFail($id, ['site', 'pipeline', 'revisions']);

        return new GeneratedContentResource($content);
    }

    /**
     * Update the specified generated content in storage (creates revision).
     */
    public function update(UpdateGeneratedContentRequest $request, string $id): GeneratedContentResource
    {
        $content = $this->findContentOrFail($id);
        $updated = $this->generationService->updateContent(
            $content,
            $request->validated(),
            Auth::id()
        );

        return new GeneratedContentResource($updated->load(['site', 'revisions']));
    }

    /**
     * Update approval status of generated content.
     */
    public function updateStatus(UpdateStatusRequest $request, string $id): GeneratedContentResource
    {
        $content = $this->findContentOrFail($id);
        $updated = $this->generationService->updateStatus($content, $request->input('status'));

        return new GeneratedContentResource($updated->load(['site']));
    }

    /**
     * Display revisions history for a generated content.
     */
    public function revisions(string $id): AnonymousResourceCollection
    {
        $content = $this->findContentOrFail($id);
        $revisions = ContentRevision::where('generated_content_id', $content->id)
            ->latest('id')
            ->paginate(15);

        return ContentRevisionResource::collection($revisions);
    }

    /**
     * Display AI request execution log history.
     */
    public function logs(Request $request): AnonymousResourceCollection
    {
        $query = AIRequestLog::with(['prompt', 'topic'])->latest('id');

        if (Auth::user()->role !== 1) {
            $query->where('customer_id', Auth::user()->customer_id);
        }

        if ($request->filled('provider')) {
            $query->where('provider', $request->input('provider'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $logs = $query->paginate($request->input('limit', 15));

        return AIRequestLogResource::collection($logs);
    }

    /**
     * Find a generated content by ID while enforcing tenant isolation.
     */
    private function findContentOrFail(string $id, array $relations = []): GeneratedContent
    {
        $query = GeneratedContent::query();
        if (! empty($relations)) {
            $query->with($relations);
        }

        if (Auth::user()->role !== 1) {
            $query->whereHas('site', function ($q) {
                $q->where('customer_id', Auth::user()->customer_id);
            });
        }

        return $query->findOrFail($id);
    }
}
