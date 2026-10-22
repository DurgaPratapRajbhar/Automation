<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    public const OWNER_SLUG = 'owner';

    protected $fillable = [
        'workspace_id',
        'name',
        'slug',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permission'
        );
    }

    // Is role wale workspace members
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'workspace_user',
            'role_id',
            'user_id'
        );
    }

    // Owner = workspace ka super admin, saari permissions automatically milti hain
    public function isOwner(): bool
    {
        return $this->is_system && $this->slug === self::OWNER_SLUG;
    }
}
