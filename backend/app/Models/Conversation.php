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
        'status',
        'unread_count',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime', 'unread_count' => 'integer'];
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
