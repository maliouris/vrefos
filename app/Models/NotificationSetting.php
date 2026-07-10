<?php

namespace App\Models;

use App\Enums\FeverLevel;
use App\Enums\NotifyFrom;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class NotificationSetting extends Model
{
    use HasFactory;

    protected $fillable = ['baby_action_type_id', 'all_children', 'enabled', 'notify_after_minutes', 'notify_from', 'title', 'description'];

    protected $casts = [
        'all_children' => 'boolean',
        'enabled' => 'boolean',
        'notify_after_minutes' => 'integer',
        'notify_from' => NotifyFrom::class,
    ];

    public function babyActionType(): BelongsTo
    {
        return $this->belongsTo(BabyActionType::class);
    }

    public function babies(): BelongsToMany
    {
        return $this->belongsToMany(Baby::class);
    }

    public function feverLevelConditions(): HasMany
    {
        return $this->hasMany(NotificationSettingFeverLevel::class);
    }

    /**
     * The fever levels this rule targets; empty = every reading.
     *
     * @return Collection<int, FeverLevel>
     */
    public function feverLevels(): Collection
    {
        return $this->feverLevelConditions->pluck('fever_level');
    }

    public function targetMedications(): BelongsToMany
    {
        return $this->belongsToMany(Medication::class)->wherePivot('excluded', false);
    }

    public function excludedMedications(): BelongsToMany
    {
        return $this->belongsToMany(Medication::class)->wherePivot('excluded', true);
    }

    public function medicationCategories(): BelongsToMany
    {
        return $this->belongsToMany(MedicationCategory::class);
    }
}
