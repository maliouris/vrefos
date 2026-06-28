<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->boolean('all_children')->default(true)->after('baby_action_type_id');
        });

        Schema::create('baby_notification_setting', function (Blueprint $table) {
            $table->foreignId('baby_id')->constrained()->onDelete('cascade');
            $table->foreignId('notification_setting_id')->constrained()->onDelete('cascade');
            $table->unique(['baby_id', 'notification_setting_id']);
        });

        // Existing rules target all children: attach every current baby so they keep firing.
        $babyIds = DB::table('babies')->pluck('id');
        $settingIds = DB::table('notification_settings')->pluck('id');

        $rows = [];

        foreach ($settingIds as $settingId) {
            foreach ($babyIds as $babyId) {
                $rows[] = [
                    'notification_setting_id' => $settingId,
                    'baby_id' => $babyId,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('baby_notification_setting')->insert($rows);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('baby_notification_setting');

        Schema::table('notification_settings', function (Blueprint $table) {
            $table->dropColumn('all_children');
        });
    }
};
