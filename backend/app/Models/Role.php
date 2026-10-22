<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A role inside one workspace (Spatie team = workspace).
 */
class Role extends SpatieRole
{
    public const OWNER_SLUG = 'owner';

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

    // Owner = workspace admin; system roles cannot be edited or deleted
    public function isOwner(): bool
    {
        return $this->is_system && $this->slug === self::OWNER_SLUG;
    }
}
