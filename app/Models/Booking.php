<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'venue_id',
        'event_id',
        'game_id',
        'user_name',            // ✅ ጨምር
        'email',                // ✅ ጨምር
        'phone_number',
        'start_time',
        'end_time',
        'sport_type',
        'payment_method',
        'transaction_ref',
        'payment_screenshot',
        'total_price',
        'number_of_players',    // ✅ ጨምር (Flutter ይልካል)
        'status',               // pending, confirmed, cancelled, completed
        'payment_status',       // ✅ ጨምር: pending, paid, failed
        'special_requests',
        'cancelled_at',
        'confirmed_at',
        'completed_at',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'total_price' => 'decimal:2',
        'number_of_players' => 'integer',
        'cancelled_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ============================================
    // 🔗 RELATIONSHIPS
    // ============================================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    // ============================================
    // 📊 STATUS HELPERS
    // ============================================

    public function isActive()
    {
        return $this->status === 'confirmed' && $this->end_time > now();
    }

    public function isUpcoming()
    {
        return $this->status === 'confirmed' && $this->start_time > now();
    }

    public function isPast()
    {
        return $this->end_time < now();
    }

    public function isPaid()           // ✅ ጨምር
    {
        return $this->payment_status === 'paid';
    }

    public function isPending()        // ✅ ጨምር
    {
        return $this->status === 'pending';
    }

    public function isConfirmed()      // ✅ ጨምር
    {
        return $this->status === 'confirmed';
    }
    
}