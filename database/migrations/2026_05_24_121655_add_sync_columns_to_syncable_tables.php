<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['babies', 'baby_actions', 'baby_action_eat_details', 'notification_settings'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
                $table->timestamp('synced_at')->nullable()->after('updated_at');
            });

            // Backfill UUIDs for existing rows
            DB::table($table)->whereNull('uuid')->orderBy('id')->each(
                fn (object $row) => DB::table($table)
                    ->where('id', $row->id)
                    ->update(['uuid' => (string) Str::uuid()])
            );
        }
    }

    public function down(): void
    {
        foreach (['babies', 'baby_actions', 'baby_action_eat_details', 'notification_settings'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropUnique(['uuid']);
                $table->dropColumn(['uuid', 'synced_at']);
            });
        }
    }
};
