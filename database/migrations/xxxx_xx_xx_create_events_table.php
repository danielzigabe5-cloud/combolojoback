<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category'); // Football, Basketball, Tennis, etc.
            $table->string('status')->default('Upcoming'); // Upcoming, Ongoing, Completed
            $table->string('type'); // Team Registration, Individual
            $table->string('date');
            $table->string('location');
            $table->string('price');
            $table->string('image_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};