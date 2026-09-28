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
        Schema::create('venue_schedules', function (Blueprint $table) {
            $table->id();

            // Cascade delete when venue is removed
            $table->foreignId('venue_id')
                  ->constrained('venues')
                  ->onDelete('cascade');

            // 0 = Sunday, 1 = Monday, … 6 = Saturday
            $table->tinyInteger('day_of_week');

            // Opening & closing times
            $table->time('open_time');
            $table->time('close_time');

            // Closed flag (for holidays / rest days)
            $table->boolean('is_closed')->default(false);

            $table->timestamps();

            // 🆕 Prevent duplicate days for the same venue
            $table->unique(['venue_id', 'day_of_week'], 'venue_day_unique');

            // 🆕 Faster lookups by venue
            $table->index('venue_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venue_schedules');
    }
};