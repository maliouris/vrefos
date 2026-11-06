<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BabyActionType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_instant',
    ];

    protected $casts = [
        'is_instant' => 'boolean',
    ];

    /** Heroicon used for this action type everywhere in the UI. */
    public static function iconFor(?string $name): string
    {
        return match (strtolower((string) $name)) {
            'eat' => 'o-cake',
            'sleep' => 'o-moon',
            'temperature' => 'o-fire',
            'medication' => 'o-beaker',
            default => 'o-clock',
        };
    }

    /** Colour slug (matches the --color-{slug} tokens and .type-chip-{slug} classes). */
    public static function slugFor(?string $name): string
    {
        return match (strtolower((string) $name)) {
            'eat' => 'eat',
            'sleep' => 'sleep',
            'temperature' => 'temperature',
            'medication' => 'medication',
            default => 'other',
        };
    }

    public function slug(): string
    {
        return self::slugFor($this->name);
    }

    public function icon(): string
    {
        return self::iconFor($this->name);
    }

    public function babyActions(): HasMany
    {
        return $this->hasMany(BabyAction::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }
}
