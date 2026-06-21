<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['babies', 'baby_actions', 'baby_action_eat_details', 'notification_settings'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['uuid']);
                $table->dropColumn(['uuid', 'synced_at']);
            });
        }
    }

    public function down(): void
    {
        // Intentionally one-way — no rollback for sync column removal.
    }
};
