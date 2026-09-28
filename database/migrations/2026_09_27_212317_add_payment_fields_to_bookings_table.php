<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // ✅ Email ለ Chapa
            if (!Schema::hasColumn('bookings', 'email')) {
                $table->string('email')->nullable()->after('phone_number');
            }

            // ✅ User Name (ለ guest bookings)
            if (!Schema::hasColumn('bookings', 'user_name')) {
                $table->string('user_name')->nullable()->after('user_id');
            }

            // ✅ Number of Players
            if (!Schema::hasColumn('bookings', 'number_of_players')) {
                $table->integer('number_of_players')->default(1)->after('total_price');
            }

            // ✅ Payment Status
            if (!Schema::hasColumn('bookings', 'payment_status')) {
                $table->string('payment_status')->default('pending')->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'email',
                'user_name',
                'number_of_players',
                'payment_status',
            ]);
        });
    }
};