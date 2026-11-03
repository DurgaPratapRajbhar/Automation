<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Something the business sells: the bot answers price and availability from these, never from its own guess
class Product extends Model
{
    protected $fillable = ['workspace_id', 'name', 'price', 'unit', 'stock', 'duration_minutes', 'active'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'stock' => 'integer', 'duration_minutes' => 'integer', 'active' => 'boolean'];
    }

    // Can one be sold now?
    public function available(): bool
    {
        return $this->active && ($this->stock === null || $this->stock > 0);
    }
}
