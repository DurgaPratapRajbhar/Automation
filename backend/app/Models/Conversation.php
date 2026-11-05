<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $fillable = [
        'workspace_id',
        'channel_id',
        'customer_phone',
        'customer_name',
        'customer_language',
        'lead_status',
        'lead_answers',
        'lead_summary',
        'status',
        'unread_count',
        'last_message_at',
        'bot_paused_until',
    ];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime', 'bot_paused_until' => 'datetime', 'unread_count' => 'integer', 'lead_answers' => 'array'];
    }

    // The owner is talking in this chat (or was asked for): no agent answers the customer
    public function botPaused(): bool
    {
        return $this->bot_paused_until !== null && $this->bot_paused_until->isFuture();
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    // One conversation → many messages
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    // Inbox list me aakhri message dikhane ke liye
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }
}
