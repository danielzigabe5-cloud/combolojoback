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

    // ✅ የምስል URL
    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return asset('storage/' . $this->image);
        }
        return null;
    }

    // ✅ Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}