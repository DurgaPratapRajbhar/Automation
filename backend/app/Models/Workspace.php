<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Workspace extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'status',
        'phone',
        'address',
        'state_code',
        'gst_status',
        'gstin',
        'upi_id',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'workspace_user'
        )
        ->withTimestamps();
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }
}