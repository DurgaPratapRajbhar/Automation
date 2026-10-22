<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends Model
{
    protected $fillable = [
        'workspace_id',
        'name',
        'phone',
        'address',
        'gstin',
        'state_code',
        'credit_limit',
        'whatsapp_opt_in',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'whatsapp_opt_in' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
