<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_id',
        'day_of_week',   // 0 = Sunday, 1 = Monday, … 6 = Saturday
        'open_time',
        'close_time',
        'is_closed',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_closed'   => 'boolean',
    ];

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }
}