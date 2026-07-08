<?php

namespace App\Modules\TopicManager\Services;

use App\Modules\SubscriptionManager\Models\Subscription;
use App\Modules\SubscriptionManager\Services\EntitlementService;
use App\Modules\TopicManager\Models\Topic;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class TopicService
{
    public function __construct(protected EntitlementService $entitlements) {}

    /**
     * Get paginated list of topics with filters, search, and sorting.
     */
    public function getPaginated(array $filters, int $limit = 15): LengthAwarePaginator
    {
        $query = Topic::query()->with(['parent', 'prompt']);

        // Search by name or category
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        // Filter by parent_id (categories/subcategories)
        if (isset($filters['parent_id'])) {
            $query->where('parent_id', $filters['parent_id']);
        }

        // Filter by status
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by language
        if (! empty($filters['language'])) {
            $query->where('language', $filters['language']);
        }

        // Filter by customer_id
        if (! empty($filters['customer_id'])) {
            $query->whereHas('subscription', function ($q) use ($filters) {
                $q->where('customer_id', $filters['customer_id']);
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $allowedSort = ['created_at', 'name', 'priority', 'status'];

        if (in_array($sortBy, $allowedSort, true)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        return $query->paginate($limit);
    }

    /**
     * Create a new topic config.
     */
    public function createTopic(array $data): Topic
    {
        if (! empty($data['subscription_id'])) {
            $subscription = Subscription::findOrFail($data['subscription_id']);
            $this->entitlements->activeSubscription($subscription->customer_id);
            $this->entitlements->assertCanCreateTopic($subscription);
        }

        $this->preventDuplicate(
            $data['name'],
            $data['parent_id'] ?? null,
            null,
            $data['subscription_id'] ?? null,
        );

        try {
            return Topic::create($data);
        } catch (\Exception $e) {
            Log::error('Failed to create topic: '.$e->getMessage());
            throw new \RuntimeException('Could not register topic config.', 0, $e);
        }
    }

    /**
     * Update an existing topic.
     */
    public function updateTopic(Topic $topic, array $data): Topic
    {
        if (! empty($data['name']) && $data['name'] !== $topic->name) {
            $parentId = array_key_exists('parent_id', $data) ? $data['parent_id'] : $topic->parent_id;
            $this->preventDuplicate(
                $data['name'],
                $parentId,
                $topic->id,
                $data['subscription_id'] ?? $topic->subscription_id,
            );
        }

        try {
            $topic->update($data);

            return $topic;
        } catch (\Exception $e) {
            Log::error('Failed to update topic: '.$e->getMessage());
            throw new \RuntimeException('Could not update topic config.', 0, $e);
        }
    }

    /**
     * Soft delete a topic.
     */
    public function deleteTopic(Topic $topic): void
    {
        $topic->delete();
    }

    /**
     * Restore a soft-deleted topic.
     */
    public function restoreTopic(string $id): Topic
    {
        $topic = Topic::onlyTrashed()->findOrFail($id);
        $topic->restore();

        return $topic;
    }

    /**
     * Prevent duplicate topics inside the same category/parent.
     */
    protected function preventDuplicate(
        string $name,
        ?int $parentId = null,
        ?int $excludeId = null,
        ?int $subscriptionId = null,
    ): void {
        $query = Topic::where('name', $name);

        $subscriptionId
            ? $query->where('subscription_id', $subscriptionId)
            : $query->whereNull('subscription_id');

        if ($parentId) {
            $query->where('parent_id', $parentId);
        } else {
            $query->whereNull('parent_id');
        }

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException("A topic with the name '{$name}' already exists in this category.");
        }
    }
}
