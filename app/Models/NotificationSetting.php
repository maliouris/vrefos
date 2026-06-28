<?php

namespace App\Models;

use App\Enums\NotifyFrom;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationSetting extends Model
{
    use HasFactory;

    protected $fillable = ['baby_action_type_id', 'enabled', 'notify_after_minutes', 'notify_from', 'title', 'description'];

    protected $casts = [
        'enabled' => 'boolean',
        'notify_after_minutes' => 'integer',
        'notify_from' => NotifyFrom::class,
    ];

    public function babyActionType(): BelongsTo
    {
        return $this->belongsTo(BabyActionType::class);
    }
}
