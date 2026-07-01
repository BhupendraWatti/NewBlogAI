<?php

namespace App\Modules\CustomerManager\Policies;

use App\Models\User;
use App\Modules\CustomerManager\Models\Customer;

class CustomerPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Super Admin (1), Admin (2), and Support (3) can view customer listings
        return in_array($user->role, [1, 2, 3]);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Customer $customer): bool
    {
        return in_array($user->role, [1, 2, 3]);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Super Admin (1) and Admin (2) can register new customers
        return in_array($user->role, [1, 2]);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Customer $customer): bool
    {
        // Super Admin (1) and Admin (2) can modify records
        return in_array($user->role, [1, 2]);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Customer $customer): bool
    {
        // Only Super Admin (1) can soft-delete records
        return $user->role === 1;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Customer $customer): bool
    {
        // Only Super Admin (1) can restore records
        return $user->role === 1;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Customer $customer): bool
    {
        return $user->role === 1;
    }

    /**
     * Determine whether the user can add notes to the customer model.
     */
    public function addNote(User $user, Customer $customer): bool
    {
        return in_array($user->role, [1, 2, 3]);
    }
}
