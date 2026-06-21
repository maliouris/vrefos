<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            // Drop the FK first — MySQL won't drop the unique index while the FK references it.
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id', 'baby_action_type_id']);
            $table->dropColumn('user_id');
            $table->unique('baby_action_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->dropUnique(['baby_action_type_id']);
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unique(['user_id', 'baby_action_type_id']);
        });
    }
};
