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
        Schema::create('baby_action_medication_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('baby_action_id')->constrained()->onDelete('cascade');
            // Restrict: a medication referenced by logged actions can't be deleted.
            $table->foreignId('medication_id')->constrained()->restrictOnDelete();
            $table->decimal('amount_ml', 6, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('baby_action_medication_details');
    }
};
