<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payouts')) {
            Schema::create('payouts', function (Blueprint $table) {
                $table->id();
                $table->string('transaction_id')->unique();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->decimal('amount', 12, 2);
                $table->string('method');
                $table->string('account_number');
                $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
                $table->timestamps();
            });
        }

        // በ users table ላይ ባላንስ ለመጨመር
        if (!Schema::hasColumn('users', 'balance')) {
            Schema::table('users', function (Blueprint $table) {
                $table->decimal('balance', 12, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};