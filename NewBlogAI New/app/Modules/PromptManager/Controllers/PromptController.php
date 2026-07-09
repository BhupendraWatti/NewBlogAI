<?php

namespace App\Modules\PromptManager\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\PromptManager\Requests\StorePromptRequest;
use App\Modules\PromptManager\Requests\UpdatePromptRequest;
use App\Modules\PromptManager\Resources\PromptResource;
use App\Modules\SubscriptionManager\Services\EntitlementService;
use App\Modules\TopicManager\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PromptController extends Controller
{
    public function __construct(protected EntitlementService $entitlements) {}

    /**
     * Display a listing of prompts with filters, sorting, and pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Prompt::query()->latest();

        // Filtering by category
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        // Filtering by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Search in name or prompt content
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('prompt', 'like', "%{$search}%");
            });
        }

        $limitInput = $request->input('limit', 15);
        if ($limitInput !== null && $limitInput !== '' && filter_var($limitInput, FILTER_VALIDATE_INT) === false) {
            abort(422, 'The limit parameter must be a positive integer.');
        }

        $limit = max(1, min((int) $limitInput, 100));

        return PromptResource::collection($query->paginate($limit));
    }

    /**
     * Store a newly created prompt template.
     */
    public function store(StorePromptRequest $request): JsonResponse
    {
        $validated = $request->validated();
        if (! empty($validated['topic_id'])) {
            $this->entitlements->assertCanCreatePrompt(Topic::findOrFail($validated['topic_id']));
        }

        $prompt = Prompt::create($validated);

        return (new PromptResource($prompt))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified prompt.
     */
    public function show(string $id): PromptResource
    {
        return new PromptResource(Prompt::findOrFail($id));
    }

    /**
     * Update the specified prompt in database.
     */
    public function update(UpdatePromptRequest $request, string $id): PromptResource
    {
        $prompt = Prompt::findOrFail($id);
        $validated = $request->validated();
        if (! empty($validated['topic_id']) && $validated['topic_id'] !== $prompt->topic_id) {
            $this->entitlements->assertCanCreatePrompt(Topic::findOrFail($validated['topic_id']));
        }

        $prompt->update($validated);

        return new PromptResource($prompt);
    }

    /**
     * Remove the specified prompt from database.
     */
    public function destroy(string $id): JsonResponse
    {
        Prompt::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Prompt template deleted successfully.',
        ]);
    }

    /**
     * Test a prompt template with mock variable inputs (dry-run).
     */
    public function test(Request $request, string $id): JsonResponse
    {
        $prompt = Prompt::findOrFail($id);
        $variables = $request->input('variables', []);

        // Retrieve default provider or the one passed in variables
        $providerId = $request->input('ai_provider_id');
        $provider = $providerId ? \App\Modules\AIProviderManager\Models\AIProvider::find($providerId) : \App\Modules\AIProviderManager\Models\AIProvider::where('is_default', true)->first();

        if (!$provider || !$provider->is_enabled || empty($provider->api_key)) {
            return response()->json([
                'message' => 'No active AI Provider with API key configured.',
            ], 422);
        }

        // Add standard fallback mock variables if not provided
        $mockVars = array_merge([
            'category' => 'Technology',
            'keywords' => 'AI, tech, innovation',
            'language' => 'en',
            'website' => 'https://example.com',
            'tone' => 'Professional',
            'date' => now()->format('F j, Y'),
            'headline' => 'AI Revolutionizes Modern Workflows',
            'summary' => 'Artificial intelligence tools are drastically transforming productivity and software development across sectors.',
            'sources' => 'https://techcrunch.com',
        ], $variables);

        // Compile prompt
        $promptEngine = app(\App\Modules\ContentPipeline\Services\PromptEngine::class);
        $compiledPrompt = $promptEngine->compileUserPrompt($prompt->prompt, $mockVars);

        try {
            $driver = app(\App\Modules\AIProviderManager\Services\AIProviderService::class)->getDriver($provider->provider_key);
            
            // Limit output to avoid excessive costs during testing
            $result = $driver->generate($provider->api_key, $compiledPrompt, $provider->default_model, [
                'max_tokens' => 500
            ]);

            return response()->json([
                'text' => $result['text'] ?? '',
                'compiled_prompt' => $compiledPrompt
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'AI generation failed: ' . $e->getMessage()
            ], 502);
        }
    }
}
