<?php

use App\Enums\NotifyFrom;
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
            // Drop the single-rule constraint so a type can have many rules.
            $table->dropUnique(['baby_action_type_id']);
            $table->string('message')->nullable()->after('notify_from');
            $table->index('baby_action_type_id');
        });

        // Backfill: ensure every action type still has at least one default rule
        // (replaces the old lazy firstOrCreate default in the scheduler).
        $typeIdsWithRules = DB::table('notification_settings')
            ->distinct()
            ->pluck('baby_action_type_id')
            ->all();

        $missingTypeIds = DB::table('baby_action_types')
            ->whereNotIn('id', $typeIdsWithRules ?: [0])
            ->pluck('id');

        foreach ($missingTypeIds as $typeId) {
            DB::table('notification_settings')->insert([
                'baby_action_type_id' => $typeId,
                'enabled' => true,
                'notify_after_minutes' => 180,
                'notify_from' => NotifyFrom::StartedAt->value,
                'message' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->dropIndex(['baby_action_type_id']);
            $table->dropColumn('message');
            $table->unique('baby_action_type_id');
        });
    }
};
