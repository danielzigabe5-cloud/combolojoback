<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ✅ google_id ካልሆነ ብቻ ጨምር
            if (!Schema::hasColumn('users', 'google_id')) {
                $table->string('google_id')->nullable()->unique()->after('email');
            }

            // ✅ avatar ካልሆነ ብቻ ጨምር
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->after('google_id');
            }

            // ✅ password ን nullable አድርግ
            if (Schema::hasColumn('users', 'password')) {
                $table->string('password')->nullable()->change();
            }

            // ❌ 'phone' ን አትንካ — 'phone_number' አለ!
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'avatar']);
        });
    }
};