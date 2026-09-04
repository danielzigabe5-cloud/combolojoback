<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venue extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'owner_id',
        'user_id',
        'name',
        'description',
        'location',
        'city',
        'sub_city',
        'capacity',
        'price_per_hour',
        'image',
        'sport_types',
        'facilities',
        'is_active',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'integer',
        'price_per_hour' => 'decimal:2',
        'sport_types' => 'array',
        'facilities' => 'array',
        'approved_at' => 'datetime',
    ];

    // ✅ ሙሉ የምስል URL ያመጣል - የአሁኑን ሰርቨር አድራሻ ይጠቀማል
    public function getImageUrlAttribute()
    {
        if ($this->image) {
            // ሙሉ URL ይመልሱ (አሁን ያለውን ሰርቨር አድራሻ ይጠቀማል)
            return asset('storage/' . $this->image);
        }
        return null;
    }

    // ✅ ለFrontend ተጨማሪ መረጃ
    public function toArray()
    {
        $array = parent::toArray();
        $array['image_url'] = $this->image_url;
        $array['image_full_url'] = $this->image_url; // ተጨማሪ
        return $array;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}