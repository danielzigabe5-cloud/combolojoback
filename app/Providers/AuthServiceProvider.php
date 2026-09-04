<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Venue;
use App\Models\Event;
use App\Models\Booking;
use App\Models\Game;
use App\Policies\VenuePolicy;
use App\Policies\EventPolicy;
use App\Policies\BookingPolicy;
use App\Policies\GamePolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Venue::class => VenuePolicy::class,
        Event::class => EventPolicy::class,
        Booking::class => BookingPolicy::class,
        Game::class => GamePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
