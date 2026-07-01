<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class keys extends Model
{
    protected $table = 'keys';
    protected $fillable = ['name', 'key'];
}
