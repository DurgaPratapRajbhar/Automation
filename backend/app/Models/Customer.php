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
        'whatsapp_opt_in_at',
        'whatsapp_opt_in_source',
        'data',
        'opted_out_at',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'whatsapp_opt_in' => 'boolean',
            'whatsapp_opt_in_at' => 'datetime',
            'data' => 'array',
            'opted_out_at' => 'datetime',
        ];
    }

    // Whoever switches the permission on or off, it is recorded here, so no path can forget it: when and how it was given
    protected static function booted(): void
    {
        static::saving(function (Customer $customer) {
            if (! $customer->isDirty('whatsapp_opt_in')) {
                return;
            }

            if ($customer->whatsapp_opt_in) {
                $customer->whatsapp_opt_in_at ??= now();
                $customer->whatsapp_opt_in_source ??= 'manual';
            } else {
                $customer->whatsapp_opt_in_at = null;
                $customer->whatsapp_opt_in_source = null;
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
