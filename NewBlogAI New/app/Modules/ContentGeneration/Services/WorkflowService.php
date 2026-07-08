<?php

namespace App\Modules\ContentGeneration\Services;

use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\ContentGeneration\Exceptions\InvalidWorkflowTransitionException;
use Illuminate\Support\Facades\Log;

class WorkflowService
{
    /**
     * Map of valid state transitions.
     */
    private const TRANSITION_MAP = [
        'generated' => ['draft', 'pending_review', 'failed'],
        'draft' => ['pending_review', 'failed'],
        'pending_review' => ['approved', 'rejected', 'draft', 'failed'],
        'approved' => ['scheduled', 'published', 'pending_review', 'draft', 'failed'],
        'scheduled' => ['published', 'failed', 'approved', 'draft'],
        'published' => ['draft'],
        'rejected' => ['draft', 'pending_review'],
        'failed' => ['draft', 'pending_review', 'scheduled', 'published', 'approved'],
    ];

    /**
     * Transition a generated content model to a new status.
     *
     * @param GeneratedContent $article
     * @param string $newStatus
     * @param int|null $userId
     * @return GeneratedContent
     * @throws InvalidWorkflowTransitionException
     */
    public function transitionTo(GeneratedContent $article, string $newStatus, ?int $userId = null): GeneratedContent
    {
        $currentStatus = $article->status;

        // If status is unchanged, skip transition logic
        if ($currentStatus === $newStatus) {
            return $article;
        }

        $allowedStatuses = ['generated', 'draft', 'pending_review', 'approved', 'scheduled', 'published', 'rejected', 'failed'];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            throw new InvalidWorkflowTransitionException(
                "Invalid target workflow status: '{$newStatus}'",
                $currentStatus ?: '',
                $newStatus
            );
        }

        // If current status is null/empty, we treat it as starting from 'generated'
        $fromStatus = $currentStatus ?: 'generated';

        $validTransitions = self::TRANSITION_MAP[$fromStatus] ?? [];

        if (!in_array($newStatus, $validTransitions, true)) {
            throw new InvalidWorkflowTransitionException(
                "Invalid workflow transition from '{$fromStatus}' to '{$newStatus}'",
                $fromStatus,
                $newStatus
            );
        }

        // Record history in metadata
        $metadata = $article->metadata ?? [];
        $history = $metadata['workflow_history'] ?? [];
        $history[] = [
            'from_status' => $currentStatus,
            'to_status' => $newStatus,
            'timestamp' => now()->toIso8601String(),
            'user_id' => $userId,
        ];
        $metadata['workflow_history'] = $history;

        $article->metadata = $metadata;
        $article->status = $newStatus;
        $article->save();

        Log::info("Content status transitioned: {$fromStatus} -> {$newStatus} (Content ID: {$article->id}, User ID: {$userId})");

        return $article;
    }
}
