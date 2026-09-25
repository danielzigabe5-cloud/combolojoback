<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'push_enabled',
        'booking_updates',
        'reminders',
        'payment_updates',
        'reviews',
        'offers',
        'news',
        'quiet_hours_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
    ];

    protected $casts = [
        'push_enabled' => 'boolean',
        'booking_updates' => 'boolean',
        'reminders' => 'boolean',
        'payment_updates' => 'boolean',
        'reviews' => 'boolean',
        'offers' => 'boolean',
        'news' => 'boolean',
        'quiet_hours_enabled' => 'boolean',
    ];

    // ═══════════════════════════════════════════════════════
    // RELATIONSHIP
    // ═══════════════════════════════════════════════════════
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ═══════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════

    /**
     * ✅ ለ user የ default setting ፍጠር ወይም አምጣ
     */
    public static function forUser($userId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            [
                'push_enabled' => true,
                'booking_updates' => true,
                'reminders' => true,
                'payment_updates' => true,
                'reviews' => true,
                'offers' => false,
                'news' => false,
                'quiet_hours_enabled' => false,
            ]
        );
    }

    /**
     * ✅ አሁን notification መላክ ይቻል? (quiet hours አረጋግጥ)
     */
    public function canSendNow(): bool
    {
        if (!$this->push_enabled) return false;
        if (!$this->quiet_hours_enabled) return true;

        $now = now()->format('H:i:s');
        $start = $this->quiet_hours_start;
        $end = $this->quiet_hours_end;

        // ሌሊት የሚያቋርጥ ከሆነ (22:00 - 07:00)
        if ($start > $end) {
            return !($now >= $start || $now <= $end);
        }

        return !($now >= $start && $now <= $end);
    }

    /**
     * ✅ የተለየ type መላክ ይቻል?
     */
    public function allowsType(string $type): bool
    {
        if (!$this->canSendNow()) return false;

        return match($type) {
            'booking' => $this->booking_updates,
            'reminder' => $this->reminders,
            'payment' => $this->payment_updates,
            'review' => $this->reviews,
            'offer' => $this->offers,
            'news' => $this->news,
            default => false,
        };
    }
}