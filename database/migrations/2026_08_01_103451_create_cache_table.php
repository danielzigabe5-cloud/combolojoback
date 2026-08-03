<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ስም ካልተጨመረ
            if (!Schema::hasColumn('users', 'name')) {
                $table->string('name')->nullable()->after('id');
            }
            
            // የኢሜይል ማረጋገጫ
            if (!Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            }
            
            // የስልክ ቁጥር መስኮች
            if (!Schema::hasColumn('users', 'phone_country_code')) {
                $table->string('phone_country_code', 10)->nullable()->after('phone_number');
            }
            
            if (!Schema::hasColumn('users', 'phone_country_iso')) {
                $table->string('phone_country_iso', 2)->nullable()->after('phone_country_code');
            }
            
            if (!Schema::hasColumn('users', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')->nullable()->after('phone_country_iso');
            }
            
            // ሚና ካልተጨመረ
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('user')->after('password');
            }
            
            // የOTP ተጨማሪ መስኮች
            if (!Schema::hasColumn('users', 'otp_attempts')) {
                $table->integer('otp_attempts')->default(0)->after('otp_expires_at');
            }
            
            if (!Schema::hasColumn('users', 'otp_last_attempt_at')) {
                $table->timestamp('otp_last_attempt_at')->nullable()->after('otp_attempts');
            }
            
            // ለስላሳ ስረዛ
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'name',
                'email_verified_at',
                'phone_country_code',
                'phone_country_iso',
                'phone_verified_at',
                'role',
                'otp_attempts',
                'otp_last_attempt_at',
                'deleted_at',
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};