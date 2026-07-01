<?php

namespace App\Modules\SubscriptionManager\Repositories;

use App\Modules\SubscriptionManager\Models\Subscription;
use App\Modules\SubscriptionManager\Models\SubscriptionHistory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SubscriptionRepository
{
    /**
     * Find an active subscription by customer ID.
     */
    public function findByCustomer(string $customerId): ?Subscription
    {
        return Subscription::where('customer_id', $customerId)->first();
    }

    /**
     * Get paginated subscription history for a customer.
     */
    public function getHistory(string $customerId, int $limit = 15): LengthAwarePaginator
    {
        return SubscriptionHistory::where('customer_id', $customerId)
            ->latest()
            ->paginate($limit);
    }

    /**
     * Get all subscriptions expiring within a given number of days.
     */
    public function getExpiringSoon(int $days = 7): \Illuminate\Database\Eloquent\Collection
    {
        return Subscription::where('status', 'active')
            ->whereBetween('ends_at', [now(), now()->addDays($days)])
            ->with('customer', 'plan')
            ->get();
    }
}
