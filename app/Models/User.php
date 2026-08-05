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
        'role',
        'otp_code',
        'otp_expires_at',
        'otp_attempts',
        'otp_last_attempt_at',
        'email_verified_at',
        'phone_verified_at',
    ];

    protected $hidden = [
        'password',
        'otp_code',
        'remember_token',
    ];

    /**
     * ዳታቤዝ ውስጥ ያሉ ቀናትን ወደ Carbon Object ለመቀየር (ለጊዜ ንፅፅር ወሳኝ ነው)
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'otp_expires_at' => 'datetime', // 👈 ኦቲፒው ማለፉን ለማወቅ የግድ ያስፈልጋል
        'otp_last_attempt_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ===== ሚና ማረጋገጫ (Role Checks) =====
    public function isUser(): bool
    {
        return $this->role === 'user' || $this->role === null;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    // ===== መገለጫ ሁኔታ (Profile Status) =====
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

    // ===== OTP ተግባራት (OTP Logic) =====
    
    /**
     * ኦቲፒው ገና ያላለፈበት መሆኑን ያረጋግጣል
     */
    public function hasValidOTP(): bool
    {
        return !is_null($this->otp_code) && 
               !is_null($this->otp_expires_at) && 
               $this->otp_expires_at->isFuture();
    }

    /**
     * ተጠቃሚው ብዙ ጊዜ በስህተት ሞክሮ ታግዶ እንደሆነ ያረጋግጣል
     */
    public function canAttemptOTP(): bool
    {
        if ($this->otp_attempts >= 5) {
            // ከ30 ደቂቃ በኋላ በድጋሚ መሞከር ይችላል
            if ($this->otp_last_attempt_at && 
                $this->otp_last_attempt_at->addMinutes(30)->isPast()) {
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

    /**
     * ኦቲፒ ስራውን ሲጨርስ ዳታውን ለማጽዳት
     */
    public function clearOTP(): void
    {
        $this->otp_code = null;
        $this->otp_expires_at = null;
        $this->resetOTPAttempts();
        $this->save();
    }
}