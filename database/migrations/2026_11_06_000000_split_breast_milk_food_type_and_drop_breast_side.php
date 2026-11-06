<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('baby_action_eat_details')
            ->where('food_type', 'breast_milk')
            ->update(['food_type' => 'breast_milk_left']);

        Schema::table('baby_action_eat_details', function (Blueprint $table) {
            $table->dropColumn('breast_side');
        });
    }

    public function down(): void
    {
        Schema::table('baby_action_eat_details', function (Blueprint $table) {
            $table->string('breast_side')->nullable();
        });

        DB::table('baby_action_eat_details')
            ->whereIn('food_type', ['breast_milk_left', 'breast_milk_right'])
            ->update(['food_type' => 'breast_milk']);
    }
};
