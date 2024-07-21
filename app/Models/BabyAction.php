<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BabyAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'baby_action_type_id',
        'baby_id',
        'started_at',
        'finished_at'
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
}
