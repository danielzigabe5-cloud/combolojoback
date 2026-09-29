<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bank_name',
        'account_number',
        'account_holder',
        'branch',
        'is_default',
        'status',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected $appends = ['masked_account'];

    public function getMaskedAccountAttribute(): string
    {
        $num = (string) ($this->account_number ?? '');
        if (strlen($num) < 4) return '****';
        return '****' . substr($num, -4);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}