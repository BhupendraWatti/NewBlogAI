<?php

namespace App\Modules\SubscriptionManager\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasUuids;

    protected $table = 'transactions';

    protected $fillable = [
        'customer_id',
        'invoice_id',
        'gateway',
        'gateway_transaction_id',
        'amount',
        'currency',
        'type', // charge, refund, credit
        'status', // pending, succeeded, failed, refunded
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Relationship: Transaction belongs to Customer.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo('App\Modules\CustomerManager\Models\Customer', 'customer_id');
    }

    /**
     * Relationship: Transaction belongs to Invoice.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
