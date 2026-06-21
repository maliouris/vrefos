<?php

namespace App\Models;

use App\Enums\BreastSide;
use App\Enums\FoodType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BabyActionEatDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'baby_action_id',
        'food_type',
        'breast_side',
    ];

    protected $casts = [
        'food_type' => FoodType::class,
        'breast_side' => BreastSide::class,
    ];

    public function babyAction(): BelongsTo
    {
        return $this->belongsTo(BabyAction::class);
    }
}
