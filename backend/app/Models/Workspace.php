<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'api_key_hash',
        'api_key_hint',
    ];

    protected $hidden = [
        'api_key_hash',
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
