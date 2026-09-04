<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('venues', function (Blueprint $table) {
            // ትክክለኛውን የ latitude/longitude መጠን ይስጡ
            if (Schema::hasColumn('venues', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable()->change();
            }
            if (Schema::hasColumn('venues', 'longitude')) {
                $table->decimal('longitude', 10, 8)->nullable()->change();
            }

            // የጎደሉ አምዶችን ይጨምሩ (ካልተገኙ)
            if (!Schema::hasColumn('venues', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            }

            if (!Schema::hasColumn('venues', 'image')) {
                $table->string('image')->nullable()->after('price_per_hour');
            }

            if (!Schema::hasColumn('venues', 'sport_types')) {
                $table->json('sport_types')->nullable()->after('image');
            }

            if (!Schema::hasColumn('venues', 'facilities')) {
                $table->json('facilities')->nullable()->after('sport_types');
            }

            if (!Schema::hasColumn('venues', 'status')) {
                $table->string('status')->default('pending')->after('is_active');
            }
        });
    }

    public function down()
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'image', 'sport_types', 'facilities', 'status']);
        });
    }
};