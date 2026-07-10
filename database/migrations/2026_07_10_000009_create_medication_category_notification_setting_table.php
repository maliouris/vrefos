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
        // Category targeting for Medication-type rules: a medication matches when any of
        // its categories is in this set. No rows (and no medication rows) = any medication.
        Schema::create('medication_category_notification_setting', function (Blueprint $table) {
            $table->foreignId('notification_setting_id')->constrained()->onDelete('cascade');
            // Restrict: same guided deletion flow as rule-referenced medications.
            $table->foreignId('medication_category_id')->constrained()->restrictOnDelete();
            $table->unique(['notification_setting_id', 'medication_category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medication_category_notification_setting');
    }
};
