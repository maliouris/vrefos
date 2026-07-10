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
        Schema::table('baby_action_types', function (Blueprint $table) {
            $table->boolean('is_instant')->default(false)->after('name');
        });

        // Instant actions have a single point-in-time datetime and no finished_at.
        // No default notification rules are seeded for them.
        foreach (['Temperature', 'Medication'] as $name) {
            DB::table('baby_action_types')->insert([
                'name' => $name,
                'is_instant' => true,
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
        DB::table('baby_action_types')->whereIn('name', ['Temperature', 'Medication'])->delete();

        Schema::table('baby_action_types', function (Blueprint $table) {
            $table->dropColumn('is_instant');
        });
    }
};
