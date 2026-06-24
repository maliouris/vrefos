<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('baby_actions', function (Blueprint $table) {
            // Exact OS notification keys scheduled for this action, so they can
            // be cancelled later even after an action-type change or rule deletion.
            $table->json('scheduled_notification_keys')->nullable()->after('notification_scheduled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('baby_actions', function (Blueprint $table) {
            $table->dropColumn('scheduled_notification_keys');
        });
    }
};
