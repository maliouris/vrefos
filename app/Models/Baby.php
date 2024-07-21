<?php

namespace App\Models;

use App\Enums\Gender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Baby extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'birth_date',
        'user_id',
        'gender'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
            'birth_date' => 'date',
            'gender' => Gender::class
        ];

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function babyActions(): HasMany {
        return $this->hasMany(BabyAction::class);
    }
}
