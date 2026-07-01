<?php

namespace App\Modules\SubscriptionManager\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CustomerManager\Models\Customer;
use App\Modules\SubscriptionManager\Models\Plan;
use App\Modules\SubscriptionManager\Models\Subscription;
use App\Modules\SubscriptionManager\Models\SubscriptionHistory;
use App\Modules\SubscriptionManager\Requests\SubscribeRequest;
use App\Modules\SubscriptionManager\Services\SubscriptionService;
use App\Modules\SubscriptionManager\Resources\SubscriptionResource;
use App\Modules\SubscriptionManager\Resources\SubscriptionHistoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $service
    ) {}

    /**
     * Display a listing of subscriptions.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Subscription::class);

        $subscriptions = Subscription::with('plan')->latest()->paginate(15);

        return SubscriptionResource::collection($subscriptions);
    }

    /**
     * Create or assign subscription to a customer.
     */
    public function store(SubscribeRequest $request, string $customerId): JsonResponse
    {
        Gate::authorize('manageSubscriptions', Subscription::class);

        $customer = Customer::find($customerId);
        if (!$customer) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Customer with ID '{$customerId}' not found.");
        }

        $plan = Plan::find($request->input('plan_id'));
        $billingPeriod = $request->input('billing_period');
        $paymentToken = $request->input('payment_token');

        $subscription = $this->service->subscribe($customer, $plan, $billingPeriod, $paymentToken);

        return (new SubscriptionResource($subscription))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display subscription details for a specific customer.
     */
    public function show(string $customerId): SubscriptionResource
    {
        Gate::authorize('viewAny', Subscription::class);

        $subscription = Subscription::where('customer_id', $customerId)->first();
        if (!$subscription) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("No subscription found for customer ID '{$customerId}'.");
        }

        return new SubscriptionResource($subscription->load('plan'));
    }

    /**
     * Upgrade subscription immediately.
     */
    public function upgrade(SubscribeRequest $request, string $customerId): SubscriptionResource
    {
        Gate::authorize('manageSubscriptions', Subscription::class);

        $subscription = Subscription::where('customer_id', $customerId)->first();
        if (!$subscription) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Subscription not found.");
        }

        $newPlan = Plan::find($request->input('plan_id'));
        $billingPeriod = $request->input('billing_period');
        $paymentToken = $request->input('payment_token');

        $updated = $this->service->upgrade($subscription, $newPlan, $billingPeriod, $paymentToken);

        return new SubscriptionResource($updated);
    }

    /**
     * Downgrade subscription.
     */
    public function downgrade(SubscribeRequest $request, string $customerId): SubscriptionResource
    {
        Gate::authorize('manageSubscriptions', Subscription::class);

        $subscription = Subscription::where('customer_id', $customerId)->first();
        if (!$subscription) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Subscription not found.");
        }

        $newPlan = Plan::find($request->input('plan_id'));
        $billingPeriod = $request->input('billing_period');

        $updated = $this->service->downgrade($subscription, $newPlan, $billingPeriod);

        return new SubscriptionResource($updated);
    }

    /**
     * Pause subscription.
     */
    public function pause(string $customerId): JsonResponse
    {
        Gate::authorize('manageSubscriptions', Subscription::class);

        $subscription = Subscription::where('customer_id', $customerId)->first();
        if (!$subscription) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Subscription not found.");
        }

        $this->service->pause($subscription);

        return response()->json(['message' => 'Subscription paused successfully.']);
    }

    /**
     * Resume subscription.
     */
    public function resume(string $customerId): JsonResponse
    {
        Gate::authorize('manageSubscriptions', Subscription::class);

        $subscription = Subscription::where('customer_id', $customerId)->first();
        if (!$subscription) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Subscription not found.");
        }

        $this->service->resume($subscription);

        return response()->json(['message' => 'Subscription resumed successfully.']);
    }

    /**
     * Cancel subscription.
     */
    public function cancel(string $customerId): JsonResponse
    {
        Gate::authorize('manageSubscriptions', Subscription::class);

        $subscription = Subscription::where('customer_id', $customerId)->first();
        if (!$subscription) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Subscription not found.");
        }

        $this->service->cancel($subscription);

        return response()->json([
            'message' => 'Subscription cancelled successfully.',
            'details' => 'Gateway subscription was cancelled. Service remains active until billing period ends.'
        ]);
    }

    /**
     * View subscription change history logs.
     */
    public function history(string $customerId): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Subscription::class);

        $history = SubscriptionHistory::where('customer_id', $customerId)
            ->latest('id')
            ->get();

        return SubscriptionHistoryResource::collection($history);
    }
}
