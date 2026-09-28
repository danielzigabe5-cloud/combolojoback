<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'phone_country_code',
        'phone_country_iso',
        'password',
        'google_id',       // ✅
        'avatar',
        'role',
        'otp_code',
        'otp_expires_at',
        'otp_attempts',
        'otp_last_attempt_at',
        'email_verified_at',
        'phone_verified_at',

        // 🆕 Admin Users page fields
        'status',          // active | pending | blocked
        'is_active',       // boolean
        'city',            // optional location
        'bank_name',       // partner bank account
        'bank_account_number',
        'bank_account_name',
    ];

    protected $hidden = [
        'password',
        'otp_code',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at'    => 'datetime',
        'phone_verified_at'    => 'datetime',
        'otp_expires_at'       => 'datetime',
        'otp_last_attempt_at'  => 'datetime',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
        'deleted_at'           => 'datetime',

        // 🆕 New casts
        'is_active' => 'boolean',
    ];

    /* ============================================================
       ROLE CHECKS
       ============================================================ */
    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isOwner(): bool
    {
        return $this->role === 'partner';
    }

    // 🆕 Accept both `owner` and `partner` role names
    public function isPartner(): bool
    {
        return in_array($this->role, ['owner', 'partner'], true);
    }

    /* ============================================================
       PROFILE STATUS
       ============================================================ */
    public function isProfileComplete(): bool
    {
        return !empty($this->name) && !empty($this->password);
    }

    public function isEmailVerified(): bool
    {
        return !is_null($this->email_verified_at);
    }

    public function isPhoneVerified(): bool
    {
        return !is_null($this->phone_verified_at);
    }

    // 🆕 Status helpers for admin page
    public function isActive(): bool
    {
        return ($this->status ?? 'active') === 'active'
            && ($this->is_active ?? true) === true;
    }

    public function isBlocked(): bool
    {
        return ($this->status ?? '') === 'blocked'
            || ($this->is_active ?? true) === false;
    }

    /* ============================================================
       OTP LOGIC
       ============================================================ */
    public function hasValidOTP(): bool
    {
        return !is_null($this->otp_code)
            && !is_null($this->otp_expires_at)
            && $this->otp_expires_at->isFuture();
    }

    public function canAttemptOTP(): bool
    {
        if ($this->otp_attempts >= 5) {
            if ($this->otp_last_attempt_at
                && $this->otp_last_attempt_at->addMinutes(30)->isPast()) {
                $this->resetOTPAttempts();
                return true;
            }
            return false;
        }
        return true;
    }

    public function incrementOTPAttempts(): void
    {
        $this->otp_attempts++;
        $this->otp_last_attempt_at = now();
        $this->save();
    }

    public function resetOTPAttempts(): void
    {
        $this->otp_attempts = 0;
        $this->otp_last_attempt_at = null;
        $this->save();
    }

    public function clearOTP(): void
    {
        $this->otp_code = null;
        $this->otp_expires_at = null;
        $this->resetOTPAttempts();
    }

    /* ============================================================
       RELATIONSHIPS
       ============================================================ */
    public function notificationSetting()
    {
        return $this->hasOne(NotificationSetting::class);
    }

    // 🆕 Venues owned by this user (for partner/admin dashboards)
    public function venues()
    {
        return $this->hasMany(Venue::class, 'owner_id');
    }
    public function wallet()
{
    return $this->hasOne(Wallet::class);
}

public function payouts()
{
    return $this->hasMany(Payout::class);
}

public function transactions()
{
    return $this->hasMany(Transaction::class);
}
}