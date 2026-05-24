<?php

namespace App\Models;

use App\Enums\NotifyFrom;
use App\Traits\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationSetting extends Model
{
    use Syncable;

    protected $fillable = ['uuid', 'user_id', 'baby_action_type_id', 'enabled', 'notify_after_minutes', 'notify_from'];

    protected $casts = [
        'enabled' => 'boolean',
        'notify_from' => NotifyFrom::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function babyActionType(): BelongsTo
    {
        return $this->belongsTo(BabyActionType::class);
    }
}
