<?php

namespace App\Models;

use App\Enums\KnowledgeStatus;
use App\Enums\KnowledgeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeSource extends Model
{
    protected $fillable = [
        'workspace_id',
        'type',
        'title',
        'content',
        'file_path',
        'file_name',
        'mime_type',
        'size_bytes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => KnowledgeType::class,
            'status' => KnowledgeStatus::class,
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
