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
        Schema::create('baby_action_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('baby_actions', function (Blueprint $table) {
            $table->foreignId('baby_action_type_id')->constrained()->onUpdate('cascade')
                ->onDelete('cascade');

            $table->dropColumn('type');
        });

        \App\Models\BabyActionType::create([
            'name' => 'Eat'
        ]);
        \App\Models\BabyActionType::create([
            'name' => 'Sleep'
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('baby_action_types');

        Schema::table('baby_actions', function (Blueprint $table) {
            $table->enum('type', ['eat', 'sleep']);
        });
    }
};
