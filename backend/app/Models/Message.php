<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'workspace_id',
        'direction',
        'external_id',
        'type',
        'body',
        'payload',
        'status',
        'sent_by_user_id',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    // Kis staff ne reply kiya (null = bot)
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }
}
