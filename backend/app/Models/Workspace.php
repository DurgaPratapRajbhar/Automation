<?php

namespace App\Models;

use App\Enums\WorkspaceModule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'status',
        'phone',
        'address',
        'state_code',
        'gst_status',
        'gstin',
        'upi_id',
        'api_key_hash',
        'api_key_hint',
        'reply_style',
        'inbox_language',
        'lead_flow_enabled',
        'lead_questions',
        'ask_delivery_address',
        'owner_alerts',
        'booking_open_time',
        'booking_close_time',
        'booking_closed_days',
        'booking_capacity',
        'auto_booking_followups',
        'modules',
        'trial_ends_at',
    ];

    protected function casts(): array
    {
        return ['trial_ends_at' => 'datetime', 'modules' => 'array', 'lead_flow_enabled' => 'boolean', 'lead_questions' => 'array', 'ask_delivery_address' => 'boolean', 'owner_alerts' => 'boolean', 'booking_closed_days' => 'array', 'booking_capacity' => 'integer', 'auto_booking_followups' => 'boolean'];
    }

    // Modules switched on for this shop; null in the database means the starter set. Anything more, the super admin turns on
    public function enabledModules(): array
    {
        return $this->modules ?? WorkspaceModule::values(WorkspaceModule::starter());
    }

    public function hasModule(WorkspaceModule $module): bool
    {
        return in_array($module->value, $this->enabledModules(), true);
    }

    public function trialExpired(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isPast();
    }

    // null when the shop is not on a trial
    public function trialDaysLeft(): ?int
    {
        return $this->trial_ends_at === null ? null : max(0, (int) ceil(now()->diffInDays($this->trial_ends_at, false)));
    }

    protected $hidden = [
        'api_key_hash',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'workspace_user'
        )
            ->withTimestamps();
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }
}
