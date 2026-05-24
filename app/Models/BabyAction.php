<?php

namespace App\Models;

use App\Traits\Syncable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BabyAction extends Model
{
    use HasFactory, Syncable;

    protected $fillable = [
        'uuid',
        'baby_action_type_id',
        'baby_id',
        'started_at',
        'finished_at',
        'reminders',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function baby(): BelongsTo
    {
        return $this->belongsTo(Baby::class);
    }

    public function babyActionType(): BelongsTo
    {
        return $this->belongsTo(BabyActionType::class);
    }

    public function eatDetail(): HasOne
    {
        return $this->hasOne(BabyActionEatDetail::class);
    }
}
