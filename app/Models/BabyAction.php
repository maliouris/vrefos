<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BabyAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'baby_action_type_id',
        'baby_id',
        'started_at',
        'finished_at',
        'notification_scheduled_at',
        'scheduled_notification_keys',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'notification_scheduled_at' => 'datetime',
        'scheduled_notification_keys' => 'array',
    ];

    public function baby(): BelongsTo
    {
        return $this->belongsTo(Baby::class);
    }

    public function babyActionType(): BelongsTo
    {
        return $this->belongsTo(BabyActionType::class);
    }

    public function eatDetail(): HasOne
    {
        return $this->hasOne(BabyActionEatDetail::class);
    }
}
