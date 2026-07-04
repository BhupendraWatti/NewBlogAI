<?php

namespace App\Modules\SubscriptionManager\DTOs;

class PlanDTO
{
    public function __construct(
        public string $name,
        public float $monthlyPrice,
        public float $yearlyPrice,
        public int $maxWordPressSites,
        public int $maxTopics,
        public int $publishingScheduleLimit,
        public int $maxArticlesPerDay,
        public int $monthlyGenerationLimit,
        public string $minimumPublishingFrequency,
        public array $featureFlags,
        public int $promptTemplatesAllowed,
        public array $aiProvidersAvailable,
        public int $apiKeysAllowed,
        public int $storageLimit,
        public bool $analyticsAccess = false,
        public bool $prioritySupport = false,
        public string $status = 'active'
    ) {}

    /**
     * Build DTO from request validated payload.
     */
    public static function fromRequest(array $validated): self
    {
        return new self(
            name: $validated['name'],
            monthlyPrice: (float)$validated['monthly_price'],
            yearlyPrice: (float)$validated['yearly_price'],
            maxWordPressSites: (int)$validated['max_wordpress_sites'],
            maxTopics: (int)$validated['max_topics'],
            publishingScheduleLimit: (int)$validated['publishing_schedule_limit'],
            maxArticlesPerDay: (int)$validated['max_articles_per_day'],
            monthlyGenerationLimit: (int)($validated['monthly_generation_limit'] ?? 100),
            minimumPublishingFrequency: $validated['minimum_publishing_frequency'] ?? 'daily',
            featureFlags: $validated['feature_flags'] ?? [],
            promptTemplatesAllowed: (int)$validated['prompt_templates_allowed'],
            aiProvidersAvailable: $validated['ai_providers_available'] ?? ['openai', 'anthropic'],
            apiKeysAllowed: (int)$validated['api_keys_allowed'],
            storageLimit: (int)$validated['storage_limit'],
            analyticsAccess: (bool)($validated['analytics_access'] ?? false),
            prioritySupport: (bool)($validated['priority_support'] ?? false),
            status: $validated['status'] ?? 'active'
        );
    }

    /**
     * Convert DTO to storage array.
     */
    public function toArray(): array
    {
        return [
            'name'                      => $this->name,
            'monthly_price'             => $this->monthlyPrice,
            'yearly_price'              => $this->yearlyPrice,
            'max_wordpress_sites'       => $this->maxWordPressSites,
            'max_topics'                => $this->maxTopics,
            'publishing_schedule_limit' => $this->publishingScheduleLimit,
            'max_articles_per_day'      => $this->maxArticlesPerDay,
            'monthly_generation_limit'  => $this->monthlyGenerationLimit,
            'minimum_publishing_frequency' => $this->minimumPublishingFrequency,
            'feature_flags'             => $this->featureFlags,
            'prompt_templates_allowed'  => $this->promptTemplatesAllowed,
            'ai_providers_available'    => $this->aiProvidersAvailable,
            'api_keys_allowed'          => $this->apiKeysAllowed,
            'storage_limit'             => $this->storageLimit,
            'analytics_access'          => $this->analyticsAccess,
            'priority_support'          => $this->prioritySupport,
            'status'                    => $this->status
        ];
    }
}
