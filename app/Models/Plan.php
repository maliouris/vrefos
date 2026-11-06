<?php

namespace App\Models;

use App\Enums\NotificationTrigger;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'baby_action_type_id',
        'starts_on',
        'ends_on',
        'device_timezone',
        'all_children',
        'enabled',
        'notification_scheduled_at',
        'scheduled_notification_keys',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'all_children' => 'boolean',
        'enabled' => 'boolean',
        'notification_scheduled_at' => 'datetime',
        'scheduled_notification_keys' => 'array',
    ];

    public function babyActionType(): BelongsTo
    {
        return $this->belongsTo(BabyActionType::class);
    }

    public function babies(): BelongsToMany
    {
        return $this->belongsToMany(Baby::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PlanVersion::class);
    }

    public function notifications(): BelongsToMany
    {
        return $this->belongsToMany(Notification::class)->withPivot('trigger');
    }

    public function startNotifications(): BelongsToMany
    {
        return $this->notifications()->wherePivot('trigger', NotificationTrigger::Start->value);
    }

    public function endNotifications(): BelongsToMany
    {
        return $this->notifications()->wherePivot('trigger', NotificationTrigger::End->value);
    }

    /**
     * The recurrence rule currently in effect (today's active version).
     */
    public function currentVersion(): ?PlanVersion
    {
        return PlanVersion::activeFor($this, now());
    }
}
