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
        Schema::create('baby_actions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['eat', 'sleep']);
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->foreignId('baby_id')->constrained()->onUpdate('cascade')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('baby_actions');
    }
};
