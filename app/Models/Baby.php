<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Baby extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'birth_date',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function babyActions(): HasMany
    {
        return $this->hasMany(BabyAction::class);
    }

    public function notificationSettings(): BelongsToMany
    {
        return $this->belongsToMany(NotificationSetting::class);
    }
}
