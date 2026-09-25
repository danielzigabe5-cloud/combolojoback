<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'full_name',
        'phone',
        'team_name',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}