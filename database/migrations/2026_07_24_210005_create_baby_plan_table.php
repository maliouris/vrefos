<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baby_plan', function (Blueprint $table) {
            $table->foreignId('baby_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->constrained()->onDelete('cascade');
            $table->unique(['baby_id', 'plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baby_plan');
    }
};
