<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
         if (Schema::hasTable('venue_schedules')) {
        return;
    }
        Schema::create('venue_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->date('date')->nullable();
            $table->tinyInteger('day_of_week')->nullable(); // 0-6
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_booked')->default(false);
            $table->timestamps();

            $table->index(['venue_id', 'date']);
            $table->index(['venue_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_schedules');
    }
};