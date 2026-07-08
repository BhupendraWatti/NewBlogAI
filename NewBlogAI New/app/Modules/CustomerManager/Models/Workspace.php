<?php

namespace App\Modules\CustomerManager\Models;

use App\Modules\SiteManager\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    protected $table = 'workspaces';

    protected $fillable = [
        'name',
        'customer_id',
    ];

    /**
     * Relationship: Workspace belongs to a Customer.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Relationship: Workspace has many Sites.
     */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class, 'workspace_id');
    }

    /**
     * Relationship: Workspace has many Employees.
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'workspace_id');
    }
}
