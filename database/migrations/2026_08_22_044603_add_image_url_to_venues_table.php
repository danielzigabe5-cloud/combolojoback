<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('venues', function (Blueprint $table) {
            if (!Schema::hasColumn('venues', 'image_url')) {
                $table->string('image_url')->nullable()->after('image');
            }
            if (!Schema::hasColumn('venues', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('owner_id');
            }
        });
    }

    public function down()
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn(['image_url', 'user_id']);
        });
    }
};