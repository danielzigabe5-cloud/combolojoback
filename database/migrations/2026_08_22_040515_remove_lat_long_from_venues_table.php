<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('venues', function (Blueprint $table) {
            if (Schema::hasColumn('venues', 'latitude')) {
                $table->dropColumn('latitude');
            }
            if (Schema::hasColumn('venues', 'longitude')) {
                $table->dropColumn('longitude');
            }
        });
    }

    public function down()
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
        });
    }
};