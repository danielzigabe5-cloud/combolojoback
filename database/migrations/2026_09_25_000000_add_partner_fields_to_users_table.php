<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // ኮለምኖቹ ከሌሉ ብቻ እንዲጨምሩ (ስህተት እንዳይመጣ)
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable();
                $table->string('business_name')->nullable();
                $table->string('bank_name')->nullable();
                $table->string('account_name')->nullable();
                $table->string('account_number')->nullable();
                $table->string('telebirr_phone')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'business_name', 'bank_name', 'account_name', 'account_number', 'telebirr_phone']);
        });
    }
};