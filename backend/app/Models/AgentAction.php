<?php

namespace App\Models;

use App\Enums\ActionStatus;
use App\Enums\ActionType;
use App\Enums\AgentName;
use Illuminate\Database\Eloquent\Model;

// Something an agent wants to do. It happens only after the owner says yes, and is checked afterwards.
class AgentAction extends Model
{
    protected $fillable = [
        'workspace_id',
        'conversation_id',
        'agent',
        'type',
        'summary',
        'payload',
        'status',
        'result',
        'error',
        'decided_by',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'agent' => AgentName::class,
            'type' => ActionType::class,
            'status' => ActionStatus::class,
            'payload' => 'array',
            'result' => 'array',
            'decided_at' => 'datetime',
        ];
    }
}
