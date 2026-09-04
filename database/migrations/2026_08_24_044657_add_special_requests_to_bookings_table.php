<?php
// database/migrations/2024_08_24_000000_add_special_requests_to_bookings_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // ✅ special_requests አምድ ይጨምሩ
            $table->text('special_requests')->nullable()->after('total_price');
            
            // ✅ ሌሎች ሊጎድሉ የሚችሉ አምዶችም ይጨምሩ
            if (!Schema::hasColumn('bookings', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('bookings', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable()->after('cancelled_at');
            }
            if (!Schema::hasColumn('bookings', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('confirmed_at');
            }
            if (!Schema::hasColumn('bookings', 'deleted_at')) {
                $table->softDeletes()->after('completed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('special_requests');
            $table->dropColumn('cancelled_at');
            $table->dropColumn('confirmed_at');
            $table->dropColumn('completed_at');
            $table->dropSoftDeletes();
        });
    }
};