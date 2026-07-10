<?php

namespace App\Models;

use App\Enums\FeverLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationSettingFeverLevel extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'notification_setting_id',
        'fever_level',
    ];

    protected $casts = [
        'fever_level' => FeverLevel::class,
    ];

    public function notificationSetting(): BelongsTo
    {
        return $this->belongsTo(NotificationSetting::class);
    }
}
