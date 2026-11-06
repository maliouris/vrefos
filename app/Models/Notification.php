<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
    ];

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class)->withPivot('trigger');
    }
}
