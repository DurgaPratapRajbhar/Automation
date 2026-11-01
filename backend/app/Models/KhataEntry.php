<?php

namespace App\Models;

use App\Enums\KhataEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KhataEntry extends Model
{
    // Ledger rows are never edited
    public const UPDATED_AT = null;

    protected $fillable = [
        'workspace_id',
        'customer_id',
        'type',
        'amount',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => KhataEntryType::class,
            'amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
