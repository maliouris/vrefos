<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationSetting extends Model
{
    protected $fillable = ['user_id', 'baby_action_type_id', 'enabled', 'notify_after_minutes'];

    protected $casts = ['enabled' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function babyActionType(): BelongsTo
    {
        return $this->belongsTo(BabyActionType::class);
    }
}
