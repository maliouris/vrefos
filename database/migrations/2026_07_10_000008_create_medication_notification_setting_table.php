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
        // Medication targeting for Medication-type rules: excluded=false rows are targets,
        // excluded=true rows never match (exclusion wins). No rows = any medication.
        Schema::create('medication_notification_setting', function (Blueprint $table) {
            $table->foreignId('notification_setting_id')->constrained()->onDelete('cascade');
            // Restrict: a medication referenced by rules can't be deleted — the UI lists
            // the rules to change first so a rule never silently widens its scope.
            $table->foreignId('medication_id')->constrained()->restrictOnDelete();
            $table->boolean('excluded')->default(false);
            $table->unique(['notification_setting_id', 'medication_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medication_notification_setting');
    }
};
