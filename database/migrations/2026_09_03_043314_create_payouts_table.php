public function up()
{
    Schema::create('payouts', function (Blueprint $table) {
        $table->id();
        $table->string('transaction_id')->unique(); // ለምሳሌ TX-90821
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->decimal('amount', 12, 2);
        $table->string('method'); // CBE, Telebirr, etc.
        $table->string('account_number');
        $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
        $table->timestamps();
    });

    // በ users table ላይ ባላንስ ለመጨመር (Optional: ካልዎት ይለፉት)
    Schema::table('users', function (Blueprint $table) {
        $table->decimal('balance', 12, 2)->default(0);
    });
}