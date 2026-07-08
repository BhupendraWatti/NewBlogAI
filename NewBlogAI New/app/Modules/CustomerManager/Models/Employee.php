<?php

namespace App\Modules\CustomerManager\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    protected $table = 'workspace_employees';

    protected $fillable = [
        'workspace_id',
        'user_id',
        'role',
    ];

    /**
     * Relationship: Employee belongs to a Workspace.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    /**
     * Relationship: Employee belongs to a User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
