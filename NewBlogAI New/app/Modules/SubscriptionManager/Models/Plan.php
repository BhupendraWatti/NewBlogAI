<?php

namespace App\Modules\SubscriptionManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use SoftDeletes;

    protected $table = 'plans';

    protected $fillable = [
        'name',
        'monthly_price',
        'yearly_price',
        'max_wordpress_sites',
        'max_topics',
        'publishing_schedule_limit',
        'max_articles_per_day',
        'monthly_generation_limit',
        'minimum_publishing_frequency',
        'feature_flags',
        'prompt_templates_allowed',
        'ai_providers_available',
        'api_keys_allowed',
        'storage_limit',
        'analytics_access',
        'priority_support',
        'status',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'max_wordpress_sites' => 'integer',
        'max_topics' => 'integer',
        'publishing_schedule_limit' => 'integer',
        'max_articles_per_day' => 'integer',
        'monthly_generation_limit' => 'integer',
        'feature_flags' => 'array',
        'prompt_templates_allowed' => 'integer',
        'ai_providers_available' => 'array',
        'api_keys_allowed' => 'integer',
        'storage_limit' => 'integer',
        'analytics_access' => 'boolean',
        'priority_support' => 'boolean',
    ];

    /**
     * Relationship: Plan has many active subscriptions.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }
}
