<?php

namespace App\Models;

use App\Enums\FoodType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BabyActionEatDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'baby_action_id',
        'food_type',
    ];

    protected $casts = [
        'food_type' => FoodType::class,
    ];

    public function babyAction(): BelongsTo
    {
        return $this->belongsTo(BabyAction::class);
    }
}
