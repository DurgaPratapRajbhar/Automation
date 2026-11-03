<?php

namespace App\Models;

use App\Enums\PromiseKind;
use App\Enums\PromiseSource;
use App\Enums\PromiseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Something a customer said they will do by a date (pay, send, call back)
class Promise extends Model
{
    protected $fillable = [
        'workspace_id',
        'conversation_id',
        'customer_name',
        'customer_phone',
        'kind',
        'quote',
        'amount',
        'due_on',
        'status',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'kind' => PromiseKind::class,
            'status' => PromiseStatus::class,
            'source' => PromiseSource::class,
            'amount' => 'decimal:2',
            'due_on' => 'date',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
