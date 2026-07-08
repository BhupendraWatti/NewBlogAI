<?php

namespace App\Modules\CustomerManager\Policies;

use App\Models\User;
use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\CustomerManager\Models\Workspace;

class GeneratedContentPolicy
{
    /**
     * Determine if the user can view any generated contents in the workspace.
     */
    public function viewAny(User $user, Workspace $workspace): bool
    {
        if (in_array($user->role, [1, 2])) {
            return true;
        }

        return $workspace->employees()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine if the user can view a specific generated content.
     */
    public function view(User $user, GeneratedContent $content, ?Workspace $workspace = null): bool
    {
        if (in_array($user->role, [1, 2])) {
            return true;
        }

        $workspace = $workspace ?? $this->resolveWorkspace($content);
        if (!$workspace) {
            return false;
        }

        return $workspace->employees()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine if the user can create/generate content in the workspace.
     */
    public function create(User $user, Workspace $workspace): bool
    {
        if (in_array($user->role, [1, 2])) {
            return true;
        }

        $employee = $workspace->employees()->where('user_id', $user->id)->first();

        return $employee && in_array($employee->role, ['Owner', 'Admin', 'Editor', 'Writer']);
    }

    /**
     * Determine if the user can generate content in the workspace.
     */
    public function generate(User $user, Workspace $workspace): bool
    {
        return $this->create($user, $workspace);
    }

    /**
     * Determine if the user can review (approve/reject) content.
     */
    public function review(User $user, GeneratedContent $content, ?Workspace $workspace = null): bool
    {
        if (in_array($user->role, [1, 2])) {
            return true;
        }

        $workspace = $workspace ?? $this->resolveWorkspace($content);
        if (!$workspace) {
            return false;
        }

        $employee = $workspace->employees()->where('user_id', $user->id)->first();

        return $employee && in_array($employee->role, ['Owner', 'Admin', 'Editor', 'Reviewer']);
    }

    /**
     * Determine if the user can publish content.
     */
    public function publish(User $user, GeneratedContent $content, ?Workspace $workspace = null): bool
    {
        if (in_array($user->role, [1, 2])) {
            return true;
        }

        $workspace = $workspace ?? $this->resolveWorkspace($content);
        if (!$workspace) {
            return false;
        }

        $employee = $workspace->employees()->where('user_id', $user->id)->first();

        return $employee && in_array($employee->role, ['Owner', 'Admin', 'Editor', 'Publisher']);
    }

    /**
     * Helper to resolve workspace from content.
     */
    protected function resolveWorkspace(GeneratedContent $content): ?Workspace
    {
        if (!$content->site_id) {
            return null;
        }

        return $content->site?->workspace;
    }
}
