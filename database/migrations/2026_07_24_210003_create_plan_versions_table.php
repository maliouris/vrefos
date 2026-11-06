<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->unsignedInteger('interval_minutes');
            $table->string('interval_unit');
            $table->json('times')->nullable();
            $table->json('weekdays')->nullable();
            $table->date('anchor_date');
            $table->string('notify_from')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->timestamps();
            $table->unique(['plan_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_versions');
    }
};
