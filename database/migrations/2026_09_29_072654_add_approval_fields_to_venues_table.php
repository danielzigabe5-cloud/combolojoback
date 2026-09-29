<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            // ✅ approved_by — ማን admin እንደፈቀደ
            if (!Schema::hasColumn('venues', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')
                    ->nullable()
                    ->after('status');
            }

            // ✅ approved_at — መቼ እንደፈቀደ
            if (!Schema::hasColumn('venues', 'approved_at')) {
                $table->timestamp('approved_at')
                    ->nullable()
                    ->after('approved_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            if (Schema::hasColumn('venues', 'approved_by')) {
                $table->dropColumn('approved_by');
            }
            if (Schema::hasColumn('venues', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
        });
    }
};