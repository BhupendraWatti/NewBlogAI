<?php

namespace App\Modules\AIProviderManager\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\AIProviderManager\Requests\StoreProviderRequest;
use App\Modules\AIProviderManager\Requests\UpdateProviderRequest;
use App\Modules\AIProviderManager\Requests\TestConnectionRequest;
use App\Modules\AIProviderManager\Resources\AIProviderResource;
use App\Modules\AIProviderManager\Services\AIProviderService;
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
        return AIProviderResource::collection(AIProvider::all());
    }

    /**
     * Store a newly created AI provider configuration in database.
     */
    public function store(StoreProviderRequest $request): JsonResponse
    {
        $provider = $this->providerService->createProvider($request->validated());

        return (new AIProviderResource($provider))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified AI provider configuration.
     */
    public function show(string $id): AIProviderResource
    {
        $provider = AIProvider::findOrFail($id);
        return new AIProviderResource($provider);
    }

    /**
     * Update the specified AI provider configuration in database.
     */
    public function update(UpdateProviderRequest $request, string $id): AIProviderResource
    {
        $provider = AIProvider::findOrFail($id);
        $updated = $this->providerService->updateProvider($provider, $request->validated());

        return new AIProviderResource($updated);
    }

    /**
     * Remove the specified AI provider configuration from database.
     */
    public function destroy(string $id): JsonResponse
    {
        $provider = AIProvider::findOrFail($id);

        if ($provider->is_default) {
            return response()->json([
                'message' => 'Cannot delete default AI provider. Please set another provider as default first.'
            ], 422);
        }

        $provider->delete();

        return response()->json([
            'message' => 'AI provider config deleted successfully.'
        ]);
    }

    /**
     * Test connection to the AI provider endpoint.
     */
    public function testConnection(TestConnectionRequest $request, string $id): JsonResponse
    {
        $provider = AIProvider::findOrFail($id);
        $apiKey = $request->input('api_key') ?: $provider->api_key;
        $model = $request->input('model') ?: $provider->default_model;

        if (empty($apiKey)) {
            return response()->json([
                'message' => 'Test failed: API Key is required but not configured.'
            ], 422);
        }

        $success = $this->providerService->testConnection($provider->provider_key, $apiKey, $model);

        if ($success) {
            return response()->json([
                'message' => 'Connection test successful!'
            ]);
        }

        return response()->json([
            'message' => 'Connection test failed. Please verify API key, model, and network parameters.'
        ], 502);
    }

    /**
     * Set default AI provider status.
     */
    public function setDefault(string $id): JsonResponse
    {
        $provider = AIProvider::findOrFail($id);
        $this->providerService->setDefault($provider);

        return response()->json([
            'message' => "{$provider->name} is now the default AI provider."
        ]);
    }
}
