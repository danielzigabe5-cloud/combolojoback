<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venue extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'owner_id', // ✅ Added
        'name',
        'description',
        'location',
        'city',
        'sub_city',
        'address',            // 🆕 Added
        'sport',              // 🆕 Added
        'opening_time',       // 🆕 Added
        'closing_time',       // 🆕 Added
        'capacity',
        'price_per_hour',
        'image',
        'image_url',
        'sport_types',
        'facilities',
        'is_active', // ✅ Added
        'status',
        'rejection_reason',
        'approved_at',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'price_per_hour' => 'decimal:2',
        'sport_types' => 'array',
        'facilities' => 'array',
        'is_active' => 'boolean', // ✅ Added cast
        'approved_at' => 'datetime',
    ];

    /* ═══════════════════════════════════════════════════════════
       ACCESSORS
       ═══════════════════════════════════════════════════════════ */

    // ✅ የምስል URL
    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return asset('storage/' . $this->image);
        }
        return null;
    }

    /* ═══════════════════════════════════════════════════════════
       RELATIONSHIPS
       ═══════════════════════════════════════════════════════════ */

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    // 🆕 Weekly schedule slots
    public function schedules()
    {
        return $this->hasMany(VenueSchedule::class)->orderBy('day_of_week');
    }

    /* ═══════════════════════════════════════════════════════════
       APPENDS
       ═══════════════════════════════════════════════════════════ */

    /**
     * Automatically append these to every JSON response
     * so the frontend always has a ready-to-use image URL.
     */
    protected $appends = ['image_url'];
}