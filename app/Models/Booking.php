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
        'start_time',
        'end_time',
        'phone_number',
        'sport_type',
        'payment_method',
        'transaction_ref',
        'payment_screenshot',
        'total_price',
        'status',
        'special_requests',
        'cancelled_at',
        'confirmed_at',
        'completed_at',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'total_price' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Relationship: Booking belongs to a User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Booking belongs to a Venue
     */
    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * Relationship: Booking belongs to an Event (optional)
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Relationship: Booking belongs to a Game (optional)
     */
    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Check if booking is active
     */
    public function isActive()
    {
        return $this->status === 'confirmed' && $this->end_time > now();
    }

    /**
     * Check if booking is upcoming
     */
    public function isUpcoming()
    {
        return $this->status === 'confirmed' && $this->start_time > now();
    }

    /**
     * Check if booking is past
     */
    public function isPast()
    {
        return $this->end_time < now();
    }
}