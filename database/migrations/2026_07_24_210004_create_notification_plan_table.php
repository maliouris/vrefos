<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_plan', function (Blueprint $table) {
            $table->foreignId('plan_id')->constrained()->onDelete('cascade');
            $table->foreignId('notification_id')->constrained()->onDelete('cascade');
            $table->string('trigger');
            $table->unique(['plan_id', 'notification_id', 'trigger']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_plan');
    }
};
