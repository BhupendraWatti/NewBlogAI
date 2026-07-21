<?php

namespace App\Modules\SubscriptionManager\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'invoices';

    protected $fillable = [
        'customer_id',
        'subscription_id',
        'invoice_number',
        'subtotal',
        'discount',
        'total',
        'currency',
        'status', // draft, unpaid, paid, void, uncollectible
        'due_at',
        'paid_at',
        'billing_reason',
        'gateway_invoice_id',
        'metadata',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Relationship: Invoice belongs to Customer.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo('App\Modules\CustomerManager\Models\Customer', 'customer_id');
    }

    /**
     * Relationship: Invoice belongs to Subscription.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    /**
     * Relationship: Invoice has many Transactions.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'invoice_id');
    }
}
