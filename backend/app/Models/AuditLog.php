<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    // Table has no updated_at — history rows are never edited
    public const UPDATED_AT = null;

    protected $fillable = [
        'workspace_id',
        'actor_user_id',
        'action',
        'subject_type',
        'subject_id',
        'changes',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',   // JSON column <-> PHP array automatically
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}