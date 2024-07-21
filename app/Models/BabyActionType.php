<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BabyActionType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name'
    ];

    public function babyActions(): HasMany
    {
        return $this->hasMany(BabyAction::class);
    }
}
