<?php

namespace App\Modules\CustomerManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerActivity extends Model
{
    protected $table = 'customer_activities';

    // Disable default timestamps since we only have created_at
    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'user_id',
        'event_type',
        'description',
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Relationship: Activity belongs to a Customer.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Relationship: Activity belongs to a User (Staff) if initiated by one.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }
}
