<?php

namespace App\Modules\CustomerManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerNote extends Model
{
    protected $table = 'customer_notes';

    protected $fillable = [
        'customer_id',
        'user_id',
        'content',
    ];

    /**
     * Relationship: Note belongs to a Customer.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Relationship: Note belongs to a User (Staff).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }
}
