<?php

namespace App\Modules\AIProviderManager\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\AIProviderManager\Requests\StoreProviderRequest;
use App\Modules\AIProviderManager\Requests\TestConnectionRequest;
use App\Modules\AIProviderManager\Requests\UpdateProviderRequest;
use App\Modules\AIProviderManager\Resources\AIProviderResource;
use App\Modules\AIProviderManager\Services\AIProviderService;
use App\Modules\ContentGeneration\Models\AIRequestLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AIProviderController extends Controller
{
    public function __construct(
        protected AIProviderService $providerService
    ) {}

    /**
     * Display a listing of the AI providers.
     */
    public function index(): AnonymousResourceCollection
    {
        return AIProviderResource::collection($this->readableProviders()->get());
    }

    /**
     * Store a newly created AI provider configuration in database.
     */
    public function store(StoreProviderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $data['customer_id'] = (int) $user->role === 1 ? null : $user->customer_id;

        abort_if((int) $user->role !== 1 && $user->customer_id === null, 422, 'A customer account is required to configure an AI provider.');

        $provider = $this->providerService->createProvider($data);

        return (new AIProviderResource($provider))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified AI provider configuration.
     */
    public function show(string $id): AIProviderResource
    {
        $provider = $this->readableProviders()->findOrFail($id);

        return new AIProviderResource($provider);
    }

    /**
     * Update the specified AI provider configuration in database.
     */
    public function update(UpdateProviderRequest $request, string $id): AIProviderResource
    {
        $provider = $this->writableProviders()->findOrFail($id);
        $updated = $this->providerService->updateProvider($provider, $request->validated());

        return new AIProviderResource($updated);
    }

    /**
     * Remove the specified AI provider configuration from database.
     */
    public function destroy(string $id): JsonResponse
    {
        $provider = $this->writableProviders()->findOrFail($id);

        if ($provider->is_default) {
            return response()->json([
                'message' => 'Cannot delete default AI provider. Please set another provider as default first.',
            ], 422);
        }

        $provider->delete();

        return response()->json([
            'message' => 'AI provider config deleted successfully.',
        ]);
    }

    /**
     * Test connection to the AI provider endpoint.
     */
    public function testConnection(TestConnectionRequest $request, string $id): JsonResponse
    {
        $provider = $this->writableProviders()->findOrFail($id);
        $apiKey = $request->input('api_key') ?: $provider->api_key;
        $model = $request->input('model') ?: $provider->default_model;

        if (empty($apiKey)) {
            return response()->json([
                'message' => 'Test failed: API Key is required but not configured.',
            ], 422);
        }

        $success = $this->providerService->testConnection($provider->provider_key, $apiKey, $model);

        if ($success) {
            return response()->json([
                'message' => 'Connection test successful!',
            ]);
        }

        return response()->json([
            'message' => 'Connection test failed. Please verify API key, model, and network parameters.',
        ], 502);
    }

    /**
     * Make a minimal API call to fetch and persist live rate-limit / credit headers.
     * This allows the dashboard to show real credit data without running a full generation.
     */
    public function refreshCredits(string $id): JsonResponse
    {
        $provider = $this->writableProviders()->findOrFail($id);

        if (empty($provider->api_key)) {
            return response()->json([
                'message' => 'No API key configured for this provider.',
            ], 422);
        }

        if (strtolower($provider->provider_key) === 'ollama') {
            return response()->json([
                'message' => 'Ollama is local — no credit tracking needed.',
                'provider' => new AIProviderResource($provider),
            ]);
        }

        try {
            $driver = $this->providerService->getDriver($provider->provider_key);
            $result = $driver->generate(
                $provider->api_key,
                'Reply with the single word: OK',
                $provider->default_model,
                ['max_tokens' => 5, 'timeout' => 30]
            );

            $limits = $result['rate_limits'] ?? [];
            $provider->handleSuccess();
            $provider->updateRateLimits(
                isset($limits['limit']) && $limits['limit'] !== null ? intval($limits['limit']) : null,
                isset($limits['remaining']) && $limits['remaining'] !== null ? intval($limits['remaining']) : null,
                $limits['reset'] ?? null
            );

            // Write an AIRequestLog entry so token/cost metrics are immediately visible
            // on the dashboard without needing a full article generation.
            AIRequestLog::create([
                'provider' => $provider->provider_key,
                'provider_id' => $provider->id,
                'customer_id' => $provider->customer_id,
                'model' => $provider->default_model,
                'prompt_tokens' => $result['prompt_tokens'] ?? 0,
                'completion_tokens' => $result['completion_tokens'] ?? 0,
                'total_tokens' => $result['total_tokens'] ?? 0,
                'estimated_cost' => $result['estimated_cost'] ?? 0,
                'execution_time_ms' => 0,
                'status' => 'success',
            ]);

            $provider->refresh();

            return response()->json([
                'message' => 'Credits refreshed successfully.',
                'provider' => new AIProviderResource($provider),
            ]);

        } catch (\Throwable $e) {
            $provider->handleFailure($e);
            $provider->refresh();

            return response()->json([
                'message' => 'Could not refresh credits: '.$e->getMessage(),
                'provider' => new AIProviderResource($provider),
            ], 502);
        }
    }

    /**
     * Set default AI provider status.
     */
    public function setDefault(string $id): JsonResponse
    {
        $provider = $this->writableProviders()->findOrFail($id);
        $this->providerService->setDefault($provider);

        return response()->json([
            'message' => "{$provider->name} is now the default AI provider.",
        ]);
    }

    private function readableProviders(): Builder
    {
        $user = auth()->user();

        return AIProvider::query()
            ->when((int) $user->role !== 1, fn (Builder $query) => $query->availableToCustomer($user->customer_id));
    }

    private function writableProviders(): Builder
    {
        $user = auth()->user();

        return AIProvider::query()
            ->when((int) $user->role !== 1, fn (Builder $query) => $query->ownedBy($user->customer_id));
    }
}
