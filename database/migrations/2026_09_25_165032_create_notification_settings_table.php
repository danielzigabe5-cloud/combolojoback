<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Master
            $table->boolean('push_enabled')->default(true);
            
            // Types
            $table->boolean('booking_updates')->default(true);
            $table->boolean('reminders')->default(true);
            $table->boolean('payment_updates')->default(true);
            $table->boolean('reviews')->default(true);
            $table->boolean('offers')->default(false);
            $table->boolean('news')->default(false);
            
            // Quiet hours
            $table->boolean('quiet_hours_enabled')->default(false);
            $table->time('quiet_hours_start')->default('22:00:00');
            $table->time('quiet_hours_end')->default('07:00:00');
            
            $table->timestamps();
            
            $table->unique('user_id'); // አንድ user አንድ setting
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};