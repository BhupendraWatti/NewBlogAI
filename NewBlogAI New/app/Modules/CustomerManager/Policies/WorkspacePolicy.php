<?php

namespace App\Modules\CustomerManager\Policies;

use App\Models\User;
use App\Modules\CustomerManager\Models\Workspace;

class WorkspacePolicy
{
    /**
     * Determine if the user can view the workspace.
     */
    public function view(User $user, Workspace $workspace): bool
    {
        // Super admin / Admin system roles
        if (in_array($user->role, [1, 2])) {
            return true;
        }

        // Check if employee is associated
        return $workspace->employees()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine if the user can create workspaces.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, [1, 2]) || !empty($user->customer_id);
    }

    /**
     * Determine if the user can update the workspace.
     */
    public function update(User $user, Workspace $workspace): bool
    {
        if (in_array($user->role, [1, 2])) {
            return true;
        }

        $employee = $workspace->employees()->where('user_id', $user->id)->first();
        
        return $employee && in_array($employee->role, ['Owner', 'Admin']);
    }

    /**
     * Determine if the user can delete the workspace.
     */
    public function delete(User $user, Workspace $workspace): bool
    {
        if ($user->role === 1) {
            return true;
        }

        $employee = $workspace->employees()->where('user_id', $user->id)->first();

        return $employee && $employee->role === 'Owner';
    }
}
