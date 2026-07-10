<?php

namespace App\Models;

use App\Enums\FeverLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BabyActionTemperatureDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'baby_action_id',
        'temperature',
    ];

    protected $casts = [
        'temperature' => 'decimal:1',
    ];

    public function babyAction(): BelongsTo
    {
        return $this->belongsTo(BabyAction::class);
    }

    public function feverLevel(): FeverLevel
    {
        // Decimal casts return strings.
        return FeverLevel::fromTemperature((float) $this->temperature);
    }
}
