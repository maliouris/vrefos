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
        Schema::dropIfExists('notification_setting_fever_levels');
        Schema::dropIfExists('medication_notification_setting');
        Schema::dropIfExists('medication_category_notification_setting');
        Schema::dropIfExists('baby_notification_setting');
        Schema::dropIfExists('notification_settings');

        Schema::table('baby_actions', function (Blueprint $table) {
            $table->dropColumn(['notification_scheduled_at', 'scheduled_notification_keys']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('baby_actions', function (Blueprint $table) {
            $table->timestamp('notification_scheduled_at')->nullable();
            $table->json('scheduled_notification_keys')->nullable();
        });
    }
};
