<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MedicationCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function medications(): BelongsToMany
    {
        return $this->belongsToMany(Medication::class);
    }
}
