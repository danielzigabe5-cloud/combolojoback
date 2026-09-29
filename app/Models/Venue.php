<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;   // 🆕 ይጨምሩ
use Illuminate\Database\Eloquent\Relations\HasMany;
class Venue extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'owner_id',
        'name',
        'description',
        'location',
        'city',
        'sub_city',
        'address',
        'sport',
        'opening_time',
        'closing_time',
        'capacity',
        'price_per_hour',
        'image',
        'image_url',
        'sport_types',
        'facilities',
        'is_active',
        'status',
        'rejection_reason',
        'approved_at',
        'approved_by',        // ✅ ተጨምሯል!
    ];

    protected $casts = [
        'capacity' => 'integer',
        'price_per_hour' => 'decimal:2',
        'sport_types' => 'array',
        'facilities' => 'array',
        'is_active' => 'boolean',
        'approved_at' => 'datetime',
    ];

    /* ═══════════════════════════════════════════════════════════
       ACCESSORS
       ═══════════════════════════════════════════════════════════ */

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

    // ✅ Approver — who approved the venue
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function schedules()
    {
        return $this->hasMany(VenueSchedule::class)->orderBy('day_of_week');
    }
    public function slots(): HasMany
{
    return $this->hasMany(Slot::class);
}

    /* ═══════════════════════════════════════════════════════════
       APPENDS
       ═══════════════════════════════════════════════════════════ */

    protected $appends = ['image_url'];
}