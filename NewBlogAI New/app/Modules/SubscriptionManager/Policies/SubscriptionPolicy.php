<?php

namespace App\Modules\SubscriptionManager\Policies;

use App\Models\User;

class SubscriptionPolicy
{
    /**
     * Determine whether the user can view listings.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [1, 2, 3]);
    }

    /**
     * Determine whether the user can manage plans (Super Admin/Admin).
     */
    public function managePlans(User $user): bool
    {
        return in_array($user->role, [1, 2]);
    }

    /**
     * Determine whether the user can manage customer subscriptions.
     */
    public function manageSubscriptions(User $user): bool
    {
        return in_array($user->role, [1, 2]);
    }
}
