<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Game;
use Illuminate\Http\Request;

class UserDashboardController extends Controller
{
    /**
     * Get user dashboard
     * GET /api/user/dashboard
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $stats = [
            'total_bookings' => Booking::where('user_id', $user->id)->count(),
            'confirmed_bookings' => Booking::where('user_id', $user->id)->where('status', 'confirmed')->count(),
            'pending_bookings' => Booking::where('user_id', $user->id)->where('status', 'pending')->count(),
            'upcoming_events' => Event::whereHas('participants', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->where('end_date', '>=', now())->count(),
            'upcoming_games' => Game::whereHas('players', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->where('end_time', '>=', now())->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'role' => $user->role,
                ],
                'stats' => $stats,
            ]
        ]);
    }

    /**
     * Get user's bookings
     * GET /api/user/bookings
     */
    public function bookings(Request $request)
    {
        $user = $request->user();
        $bookings = Booking::where('user_id', $user->id)->with('venue')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $bookings
        ]);
    }

    /**
     * Get user's events
     * GET /api/user/events
     */
    public function events(Request $request)
    {
        $user = $request->user();
        $events = Event::whereHas('participants', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $events
        ]);
    }

    /**
     * Get user's games
     * GET /api/user/games
     */
    public function games(Request $request)
    {
        $user = $request->user();
        $games = Game::whereHas('players', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $games
        ]);
    }
}
