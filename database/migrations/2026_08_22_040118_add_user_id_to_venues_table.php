<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('venues', function (Blueprint $table) {
            if (!Schema::hasColumn('venues', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('owner_id');
            }
            if (!Schema::hasColumn('venues', 'status')) {
                $table->string('status')->default('pending')->after('is_active');
            }
        });
    }

    public function down()
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'status']);
        });
    }
};