<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait Syncable
{
    public static function bootSyncable(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Scope to records not yet synced or modified since last sync.
     */
    public function scopeDirty(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('synced_at')
                ->orWhereColumn('updated_at', '>', 'synced_at');
        });
    }

    /**
     * Mark this record as successfully synced without touching updated_at.
     */
    public function markSynced(): void
    {
        $this->updateQuietly(['synced_at' => now()]);
    }
}
