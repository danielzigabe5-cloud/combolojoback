<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // ኢንዴክሱ አስቀድሞ ከሌለ ብቻ እንዲጨምር
            if (!IndexExists('users', 'users_email_index')) {
                $table->index('email');
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email']);
        });
    }
};

/**
 * ኢንዴክሱ መኖሩን ለማረጋገጥ የሚረዳ ረዳት ፋንክሽን
 */
function IndexExists($table, $index) {
    $conn = Schema::getConnection();
    $dbSchemaManager = $conn->getDoctrineSchemaManager();
    $indexes = $dbSchemaManager->listTableIndexes($table);
    return array_key_exists($index, $indexes);
}