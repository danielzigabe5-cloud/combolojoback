<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('venues')) {
            return;
        }

        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            
            // 📋 መሰረታዊ መረጃ
            $table->string('name');
            $table->text('description')->nullable();
            
            // 📍 Location
            $table->string('location');
            $table->string('city');
            $table->string('sub_city')->nullable();
            
            // ⚽ Venue Details
            $table->integer('capacity')->nullable();
            $table->decimal('price_per_hour', 10, 2)->default(0);
            
            // 🖼️ ምስል
            $table->string('image')->nullable();
            $table->string('image_url')->nullable();
            
            // 🏟️ አገልግሎቶች (JSON arrays)
            $table->json('sport_types')->nullable();
            $table->json('facilities')->nullable();
            
            // 👤 ባለቤት (ADDED THESE)
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable(); 
            
            // ✅ ሁኔታ (ADDED is_active)
            $table->boolean('is_active')->default(false); 
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};