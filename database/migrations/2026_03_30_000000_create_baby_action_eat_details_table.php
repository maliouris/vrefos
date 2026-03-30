<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baby_action_eat_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('baby_action_id')->constrained()->onDelete('cascade');
            $table->string('food_type')->nullable();
            $table->string('breast_side')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baby_action_eat_details');
    }
};
