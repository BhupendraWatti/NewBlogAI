<?php

namespace App\Modules\Publishing\Services;

use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\Publishing\Models\PublishingLog;
use App\Modules\Publishing\Jobs\PublishPostJob;
use App\Modules\SiteManager\Services\WPClientService;
use App\Modules\SiteManager\Models\Site;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use App\Modules\SubscriptionManager\Services\EntitlementService;

class PublishingService
{
    public function __construct(
        protected WPClientService $wpClient,
        protected EntitlementService $entitlements,
    ) {}

    /**
     * Get paginated list of publishing logs.
     */
    public function getPaginated(array $filters, int $limit = 15): LengthAwarePaginator
    {
        $query = PublishingLog::query()->with(['content', 'site', 'author']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['site_id'])) {
            $query->where('site_id', $filters['site_id']);
        }

        return $query->latest()->paginate($limit);
    }

    /**
     * Queue a new single publishing request.
     */
    public function queuePublish(int $articleId, array $data, ?int $userId): PublishingLog
    {
        $article = GeneratedContent::findOrFail($articleId);
        $siteId = $data['site_id'] ?? null;
        
        // If site is not specified, fall back to default website selection from DB
        if (empty($siteId)) {
            $defaultSite = Site::where('is_default', true)->where('is_active', true)->first();
            if (!$defaultSite) {
                throw new InvalidArgumentException("Destination site is missing and no default active WordPress site is configured.");
            }
            $siteId = $defaultSite->id;
        } else {
            $site = Site::findOrFail($siteId);
            if (!$site->is_active) {
                throw new InvalidArgumentException("Selected WordPress site is currently deactivated.");
            }
        }

        $site = $site ?? Site::findOrFail($siteId);
        $this->entitlements->assertCanPublish($site);

        // Prevent duplicates: disallow publishing if already successfully published to the same site
        $duplicate = PublishingLog::where('generated_content_id', $article->id)
            ->where('site_id', $siteId)
            ->whereIn('status', ['completed', 'queued', 'processing'])
            ->exists();

        if ($duplicate) {
            throw new InvalidArgumentException("This article has already been successfully published or is currently in the publishing queue for this site.");
        }

        try {
            return DB::transaction(function () use ($article, $siteId, $data, $userId) {
                $log = PublishingLog::create([
                    'generated_content_id' => $article->id,
                    'site_id'              => $siteId,
                    'user_id'              => $userId,
                    'status'               => 'pending',
                    'wp_status'            => $data['wp_status'] ?? 'draft',
                    'scheduled_at'         => $data['scheduled_at'] ?? null,
                ]);

                // Transition article approval status
                $article->update(['status' => 'pending_review']);

                // Dispatch asynchronous publish job
                PublishPostJob::dispatch($log->id);

                return $log;
            });
        } catch (\Exception $e) {
            Log::error("Failed to queue publishing: " . $e->getMessage());
            throw new \RuntimeException("Could not queue publishing request.", 0, $e);
        }
    }

    /**
     * Queue bulk publishing requests for multiple articles.
     */
    public function bulkQueuePublish(array $articleIds, array $data, ?int $userId): array
    {
        $logs = [];

        try {
            DB::transaction(function () use ($articleIds, $data, $userId, &$logs) {
                foreach ($articleIds as $id) {
                    $logs[] = $this->queuePublish($id, $data, $userId);
                }
            });
            return $logs;
        } catch (\Exception $e) {
            Log::error("Bulk publishing failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Asynchronously execute the WordPress API call from queue job.
     */
    public function executePublish(PublishingLog $log): void
    {
        $site = $log->site;
        $content = $log->content;

        if (!$site || !$content) {
            throw new \RuntimeException("Publishing log dependencies missing.");
        }

        // Determine WordPress status
        $wpStatus = $log->wp_status ?: 'draft';
        if ($log->scheduled_at && $log->scheduled_at->isFuture()) {
            $wpStatus = 'future';
        }

        // Perform the WordPress post publication/update
        $result = $this->wpClient->publishPost(
            $site,
            $content->title,
            $content->content,
            $wpStatus,
            $log->scheduled_at ? $log->scheduled_at->toDateTimeString() : null,
            $log->wp_post_id
        );

        // Update successful states inside transaction
        DB::transaction(function () use ($log, $result, $content, $wpStatus) {
            $log->update([
                'status'        => 'completed',
                'wp_post_id'    => $result['id'],
                'published_url' => $result['link'],
                'completed_at'  => now(),
                'error_message' => null,
            ]);

            // Update content status to published
            $content->update(['status' => 'published']);
        });
    }

    /**
     * Synchronize and pull published status details from remote WordPress site.
     */
    public function syncPostStatus(PublishingLog $log): void
    {
        if ($log->status !== 'completed' || empty($log->wp_post_id)) {
            throw new InvalidArgumentException("Cannot sync status for an unpublished post.");
        }

        $site = $log->site;
        if (!$site) {
            throw new \RuntimeException("Site record not found.");
        }

        $wpPost = $this->wpClient->getPost($site, $log->wp_post_id);

        if ($wpPost === null) {
            // Post has been deleted from WordPress
            DB::transaction(function () use ($log) {
                $log->update([
                    'status'        => 'failed',
                    'error_message' => 'Post was deleted or unpublished from WordPress.',
                ]);
                $log->content->update(['status' => 'draft']);
            });
            Log::info("Synced Post ID {$log->wp_post_id}: Detected deleted on remote WP.");
        } else {
            // Sync any URL or status changes
            $log->update([
                'published_url' => $wpPost['link'] ?? $log->published_url,
                'wp_status'     => $wpPost['status'] ?? $log->wp_status,
                'updated_at'    => now(),
            ]);
            Log::info("Synced Post ID {$log->wp_post_id}: Status is '{$log->wp_status}'.");
        }
    }

    /**
     * Retry a failed publishing run.
     */
    public function retryPublish(PublishingLog $log): void
    {
        if ($log->status !== 'failed') {
            throw new InvalidArgumentException("Can only retry failed publishing runs.");
        }

        try {
            DB::transaction(function () use ($log) {
                $log->update([
                    'status'        => 'pending',
                    'error_message' => null,
                ]);

                PublishPostJob::dispatch($log->id);
            });
        } catch (\Exception $e) {
            Log::error("Failed to retry publishing for log ID {$log->id}: " . $e->getMessage());
            throw new \RuntimeException("Failed to queue retry job.", 0, $e);
        }
    }

    /**
     * Cancel a pending publishing log.
     */
    public function cancelPublish(PublishingLog $log): void
    {
        if (in_array($log->status, ['completed', 'failed', 'cancelled'], true)) {
            throw new InvalidArgumentException("Cannot cancel completed, failed, or already cancelled publishing runs.");
        }

        try {
            DB::transaction(function () use ($log) {
                $log->update(['status' => 'cancelled']);
                $log->content->update(['status' => 'approved']); // reset to approved draft
            });
        } catch (\Exception $e) {
            Log::error("Failed to cancel publishing run ID {$log->id}: " . $e->getMessage());
            throw new \RuntimeException("Could not cancel publishing.", 0, $e);
        }
    }
}
