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
        Schema::create('medication_medication_category', function (Blueprint $table) {
            $table->foreignId('medication_id')->constrained()->onDelete('cascade');
            // Restrict: category deletion must detach explicitly after the user confirms
            // the "these medications will end up uncategorized" warning.
            $table->foreignId('medication_category_id')->constrained()->restrictOnDelete();
            $table->unique(['medication_id', 'medication_category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medication_medication_category');
    }
};
