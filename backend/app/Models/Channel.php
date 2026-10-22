<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Channel extends Model
{
    protected $fillable = [
        'workspace_id',
        'type',
        'provider',
        'name',
        'external_account_id',
        'status',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function credential(): HasOne
    {
        return $this->hasOne(ChannelCredential::class);
    }
}