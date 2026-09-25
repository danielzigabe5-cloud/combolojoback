<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        try {
            Schema::table('users', function (Blueprint $table) {
                $table->index('email');
            });
        } catch (\Exception $e) {
            // Index ካለ ችላ በለው
            if (!str_contains($e->getMessage(), 'Duplicate key name')) {
                throw $e;
            }
        }
    }

    public function down(): void
    {
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['email']);
            });
        } catch (\Exception $e) {
            // ካልተገኘ ችላ በለው
        }
    }
};