<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('venues', function (Blueprint $table) {
            // ነባር አምዶችን ያስተካክሉ
            if (!Schema::hasColumn('venues', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            }

            if (!Schema::hasColumn('venues', 'image')) {
                $table->string('image')->nullable()->after('price_per_hour');
            }

            if (!Schema::hasColumn('venues', 'status')) {
                $table->string('status')->default('pending')->after('is_active');
            }

            if (!Schema::hasColumn('venues', 'sport_types')) {
                $table->json('sport_types')->nullable()->after('facilities');
            }

            if (!Schema::hasColumn('venues', 'facilities')) {
                $table->json('facilities')->nullable()->after('sport_types');
            }
        });
    }

    public function down()
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'image', 'status', 'sport_types', 'facilities']);
        });
    }
};