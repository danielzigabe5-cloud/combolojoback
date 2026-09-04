// database/migrations/xxxx_add_partner_fields_to_users_table.php
public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('phone')->nullable();
        $table->string('business_name')->nullable();
        $table->string('bank_name')->nullable();
        $table->string('account_name')->nullable();
        $table->string('account_number')->nullable();
        $table->string('telebirr_phone')->nullable();
    });
}