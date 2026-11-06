<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('baby_action_type_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('device_timezone')->default('UTC');
            $table->boolean('all_children')->default(true);
            $table->boolean('enabled')->default(true);
            $table->timestamp('notification_scheduled_at')->nullable();
            $table->json('scheduled_notification_keys')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
