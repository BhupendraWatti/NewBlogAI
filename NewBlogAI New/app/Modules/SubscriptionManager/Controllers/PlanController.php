<?php

namespace App\Modules\SubscriptionManager\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SubscriptionManager\Models\Plan;
use App\Modules\SubscriptionManager\Models\Subscription;
use App\Modules\SubscriptionManager\Requests\StorePlanRequest;
use App\Modules\SubscriptionManager\DTOs\PlanDTO;
use App\Modules\SubscriptionManager\Resources\PlanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PlanController extends Controller
{
    /**
     * Display a listing of plans.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Subscription::class);

        $plans = Plan::latest()->get();

        return PlanResource::collection($plans);
    }

    /**
     * Store a newly created plan configuration.
     */
    public function store(StorePlanRequest $request): JsonResponse
    {
        Gate::authorize('managePlans', Subscription::class);

        $dto = PlanDTO::fromRequest($request->validated());
        $plan = Plan::create($dto->toArray());

        return (new PlanResource($plan))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified plan details.
     */
    public function show(string $id): PlanResource
    {
        Gate::authorize('viewAny', Subscription::class);

        $plan = Plan::find($id);
        if (!$plan) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Plan with ID '{$id}' not found.");
        }

        return new PlanResource($plan);
    }

    /**
     * Update the specified plan configuration.
     */
    public function update(StorePlanRequest $request, string $id): PlanResource
    {
        Gate::authorize('managePlans', Subscription::class);

        $plan = Plan::find($id);
        if (!$plan) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Plan with ID '{$id}' not found.");
        }

        $dto = PlanDTO::fromRequest($request->validated());
        $plan->update($dto->toArray());

        return new PlanResource($plan);
    }

    /**
     * Remove the specified plan configuration.
     */
    public function destroy(string $id): JsonResponse
    {
        Gate::authorize('managePlans', Subscription::class);

        $plan = Plan::find($id);
        if (!$plan) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Plan with ID '{$id}' not found.");
        }

        $plan->delete();

        return response()->json(['message' => "Plan '{$plan->name}' soft-deleted successfully."]);
    }
}
