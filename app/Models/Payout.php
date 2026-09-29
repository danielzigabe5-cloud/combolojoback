<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bank_account_id',      // 🆕
        'transaction_id',       // 🆕
        'reference',            // 🆕
        'amount',
        'fee',                  // 🆕
        'net_amount',           // 🆕
        'method',
        'bank_name',            // 🆕
        'account_number',
        'status',
        'notes',                // 🆕
        'rejection_reason',     // 🆕
        'requested_at',         // 🆕
        'processed_at',         // 🆕
        'processed_by',         // 🆕
        'completed_at',         // 🆕
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'fee'          => 'decimal:2',
        'net_amount'   => 'decimal:2',
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}