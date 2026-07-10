<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BabyActionMedicationDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'baby_action_id',
        'medication_id',
        'amount_ml',
    ];

    protected $casts = [
        'amount_ml' => 'decimal:2',
    ];

    public function babyAction(): BelongsTo
    {
        return $this->belongsTo(BabyAction::class);
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }
}
