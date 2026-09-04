<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('venues', function (Blueprint $table) {
            // ትክክለኛውን መጠን ይስጡ (ትልቅ decimal)
            $table->decimal('latitude', 10, 8)->nullable()->change();
            $table->decimal('longitude', 10, 8)->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->change();
            $table->decimal('longitude', 10, 7)->nullable()->change();
        });
    }
};