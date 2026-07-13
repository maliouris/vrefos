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

    public function ageLabel(): ?string
    {
        if ($this->birth_date === null) {
            return null;
        }

        $months = $this->birth_date->diffInMonths(now());

        if ($months < 1) {
            $days = (int) $this->birth_date->diffInDays(now());

            return $days <= 0 ? 'newborn' : $days.'d';
        }

        if ($months < 24) {
            return ((int) $months).' mo';
        }

        $years = (int) $this->birth_date->diffInYears(now());
        $remainingMonths = ((int) $months) - ($years * 12);

        return $remainingMonths > 0 ? $years.'y '.$remainingMonths.'mo' : $years.'y';
    }
}
