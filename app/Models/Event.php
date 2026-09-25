<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'category',
        'status',
        'type',
        'date',
        'location',
        'price',
        'image_url',
    ];

    public function registrations()
    {
        return $this->hasMany(EventRegistration::class);
    }
}