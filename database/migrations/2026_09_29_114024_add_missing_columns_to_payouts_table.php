<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            // 🎯 1. bank_account_id
            if (! Schema::hasColumn('payouts', 'bank_account_id')) {
                $table->unsignedBigInteger('bank_account_id')->nullable()->after('user_id');
            }

            // 🎯 2. reference (unique)
            if (! Schema::hasColumn('payouts', 'reference')) {
                $table->string('reference')->nullable()->unique()->after('bank_account_id');
            }

            // 🎯 3. fee
            if (! Schema::hasColumn('payouts', 'fee')) {
                $table->decimal('fee', 10, 2)->default(0)->after('amount');
            }

            // 🎯 4. net_amount
            if (! Schema::hasColumn('payouts', 'net_amount')) {
                $table->decimal('net_amount', 15, 2)->nullable()->after('fee');
            }

            // 🎯 5. bank_name
            if (! Schema::hasColumn('payouts', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('method');
            }

            // 🎯 6. notes
            if (! Schema::hasColumn('payouts', 'notes')) {
                $table->text('notes')->nullable()->after('account_number');
            }

            // 🎯 7. rejection_reason
            if (! Schema::hasColumn('payouts', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('notes');
            }

            // 🎯 8. requested_at
            if (! Schema::hasColumn('payouts', 'requested_at')) {
                $table->timestamp('requested_at')->nullable();
            }

            // 🎯 9. processed_at
            if (! Schema::hasColumn('payouts', 'processed_at')) {
                $table->timestamp('processed_at')->nullable();
            }

            // 🎯 10. processed_by
            if (! Schema::hasColumn('payouts', 'processed_by')) {
                $table->unsignedBigInteger('processed_by')->nullable();
            }

            // 🎯 11. completed_at
            if (! Schema::hasColumn('payouts', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
        });

        // 🎯 status enum ማስተካከል — 'completed', 'failed' → 'paid', 'rejected'
        DB::statement("
            ALTER TABLE payouts 
            MODIFY COLUMN status ENUM('pending', 'paid', 'rejected') 
            NOT NULL DEFAULT 'pending'
        ");

        // 🎯 transaction_id ወደ nullable ማድረግ
        DB::statement("
            ALTER TABLE payouts 
            MODIFY COLUMN transaction_id VARCHAR(255) NULL
        ");

        // 🎯 Foreign keys
        Schema::table('payouts', function (Blueprint $table) {
            if (Schema::hasColumn('payouts', 'bank_account_id')) {
                try {
                    $table->foreign('bank_account_id')
                        ->references('id')->on('bank_accounts')
                        ->onDelete('set null');
                } catch (\Exception $e) {
                    // Already exists
                }
            }
        });
    }

    public function down(): void
    {
        // 🎯 ወደ ቀድሞ ሁኔታ መመለስ
        DB::statement("
            ALTER TABLE payouts 
            MODIFY COLUMN status ENUM('pending', 'completed', 'failed') 
            NOT NULL DEFAULT 'pending'
        ");

        Schema::table('payouts', function (Blueprint $table) {
            $columns = [
                'bank_account_id', 'reference', 'fee', 'net_amount',
                'bank_name', 'notes', 'rejection_reason',
                'requested_at', 'processed_at', 'processed_by', 'completed_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('payouts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};