<?php
// backend/app/Models/OtpVerification.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    /**
     * በጅምላ ሊሞሉ የሚችሉ መስኮች
     */
    protected $fillable = [
        'email',           // ተጠቃሚው ኢሜል
        'otp',             // 6-አሃዝ ኮድ (ሃሽ ተደርጎ)
        'country_code',    // የሀገር ኮድ
        'expires_at',      // መቼ እንደሚያልፍ
        'is_verified',     // ተረጋግጧል?
        'verified_at',     // መቼ ተረጋገጠ?
        'failed_attempts', // የተሳሳቱ ሙከራዎች ብዛት
        'is_blocked',      // ተዘግቷል?
        'ip_address',      // ጥያቄ የመጣበት IP
        'user_agent',      // የተጠቃሚው መሣሪያ መረጃ
    ];

    /**
     * የመስኮችን የውሂብ አይነት መቀየር
     */
    protected $casts = [
        'expires_at' => 'datetime',    // የሚያልፍበት ቀን
        'verified_at' => 'datetime',   // የተረጋገጠበት ቀን
        'is_verified' => 'boolean',    // ተረጋግጧል?
        'is_blocked' => 'boolean',     // ተዘግቷል?
    ];

    /**
     * OTP ትክክል እና ጊዜው ያላለፈ መሆኑን ማረጋገጥ
     * @return bool
     */
    public function isValid(): bool
    {
        // ካልተረጋገጠ፣ ካልተዘጋ እና ጊዜው ካላለፈ
        return !$this->is_verified && 
               !$this->is_blocked && 
               now()->lt($this->expires_at);
    }

    /**
     * OTP ጊዜው አለፈ መሆኑን ማረጋገጥ
     * @return bool
     */
    public function isExpired(): bool
    {
        return now()->gt($this->expires_at);
    }
}