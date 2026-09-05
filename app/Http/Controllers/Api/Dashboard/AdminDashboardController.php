<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Venue;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        try {
            $stats = [
                'totalRevenue' => $this->getTotalRevenue(),
                'revenueGrowth' => $this->getRevenueGrowth(),
                'totalPartners' => $this->getTotalPartners(),
                'newPartnersThisWeek' => $this->getNewPartnersThisWeek(),
                'totalUsers' => $this->getTotalUsers(),
                'activeUsersToday' => $this->getActiveUsersToday(),
                'pendingReports' => 0,
                'totalVenues' => $this->getTotalVenues(),
                'totalGames' => 0,
                'todayBookings' => $this->getTodayBookings(),
                'completionRate' => $this->getCompletionRate(),
                'recentBookings' => $this->getRecentBookings(),
                'venuePerformance' => $this->getVenuePerformance(), // 👈 አሁን በትክክል ተጨምሯል
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Dashboard Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard data: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getTotalRevenue()
    {
        // 'completed' የሚለው በዳታቤዝህ 'Completed' (Capital C) ሊሆን ስለሚችል lowercase መሆኑን አረጋግጥ
        return Booking::whereIn('status', ['completed', 'Completed'])->sum('total_price') ?? 0;
    }

    private function getVenuePerformance()
    {
        try {
            return Venue::withCount(['bookings as total_revenue' => function ($query) {
                    $query->whereIn('status', ['completed', 'Completed'])->select(\DB::raw('sum(total_price)'));
                }])
                ->withCount('bookings')
                ->orderBy('total_revenue', 'desc')
                ->limit(5)
                ->get()
                ->map(function($venue) {
                    return [
                        'name' => $venue->name,
                        'bookings_count' => $venue->bookings_count,
                        'revenue' => (float) ($venue->total_revenue ?? 0)
                    ];
                });
        } catch (\Exception $e) {
            Log::error('Venue Performance Error: ' . $e->getMessage());
            return [];
        }
    }

    private function getRevenueGrowth()
    {
        $currentMonth = Booking::whereIn('status', ['completed', 'Completed'])->whereMonth('created_at', now()->month)->sum('total_price') ?? 0;
        $lastMonth = Booking::whereIn('status', ['completed', 'Completed'])->whereMonth('created_at', now()->subMonth()->month)->sum('total_price') ?? 0;
        return $lastMonth > 0 ? round(($currentMonth - $lastMonth) / $lastMonth * 100, 1) : 0;
    }

    private function getTotalPartners() { return User::whereIn('role', ['owner', 'partner'])->count(); }
    private function getNewPartnersThisWeek() { return User::whereIn('role', ['owner', 'partner'])->where('created_at', '>=', now()->startOfWeek())->count(); }
    private function getTotalUsers() { return User::count(); }
    private function getActiveUsersToday() { return User::whereDate('last_login_at', now()->today())->count(); }
    private function getTotalVenues() { return Venue::count(); }
    private function getTodayBookings() { return Booking::whereDate('created_at', now()->today())->count(); }
    private function getCompletionRate() {
        $total = Booking::count();
        $completed = Booking::whereIn('status', ['completed', 'Completed'])->count();
        return $total > 0 ? round(($completed / $total) * 100, 1) : 0;
    }

    private function getRecentBookings()
    {
        try {
            return Booking::with(['user', 'venue'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($booking) {
                    return [
                        'id' => $booking->id,
                        'userName' => $booking->user->name ?? 'Guest',
                        'venueName' => $booking->venue->name ?? 'Unknown',
                        'amount' => $booking->total_price ?? 0,
                        'status' => $booking->status ?? 'pending',
                        'createdAt' => $booking->created_at ? $booking->created_at->toISOString() : now()->toISOString()
                    ];
                });
        } catch (\Exception $e) {
            return [];
        }
    }
    /**
     * Get all venues (admin view)
     */
    public function allVenues(Request $request)
    {
        try {
            $query = Venue::with('user');
            
            if ($request->has('status')) {
                if ($request->status === 'active') {
                    $query->where('is_active', true);
                } elseif ($request->status === 'pending') {
                    $query->where('is_active', false);
                }
            }
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('location', 'LIKE', "%{$search}%");
            }
            
            $venues = $query->orderBy('created_at', 'desc')->get();
            
            Log::info('Admin venues fetched: ' . $venues->count());
            
            return response()->json([
                'success' => true,
                'data' => $venues
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching admin venues: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch venues: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending venues (for approval)
     */
    public function pendingVenues(Request $request)
    {
        try {
            $venues = Venue::with('owner')
                ->where('is_active', false)
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $venues
            ]);
        } catch (\Exception $e) {
            Log::error('pendingVenues error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pending venues: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a venue
     */
    public function approveVenue(Request $request, $id)
{
try {


    // 1. Find venue
    $venue = Venue::findOrFail($id);

    // 2. Get venue owner BEFORE updating venue
    $ownerId = $venue->owner_id ?? $venue->user_id;

    if (!$ownerId) {
        return response()->json([
            'success' => false,
            'message' => 'This venue does not have an owner.'
        ], 400);
    }

    // 3. Find owner
    $owner = \App\Models\User::find($ownerId);

    if (!$owner) {
        \Log::error('VENUE OWNER NOT FOUND', [
            'venue_id' => $venue->id,
            'owner_id' => $ownerId,
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Venue owner not found.',
            'owner_id' => $ownerId,
        ], 404);
    }

    // 4. Approve venue
    $venue->is_active = true;
    $venue->status = 'approved';
    $venue->approved_by = $request->user()->id;
    $venue->approved_at = now();
    $venue->save();

    // 5. Automatically promote owner to partner
    $owner->role = 'partner';
    $owner->save();
    $owner->refresh();

    // 6. Log everything
    \Log::info('VENUE APPROVED SUCCESSFULLY', [
        'venue_id' => $venue->id,
        'venue_name' => $venue->name,

        'owner_id' => $owner->id,
        'owner_email' => $owner->email,

        'role_after_update' => $owner->role,

        'approved_by' => $request->user()->id,
    ]);

    // 7. Return response
    return response()->json([
        'success' => true,
        'message' => 'Venue approved and owner promoted to partner successfully.',

        'venue' => [
            'id' => $venue->id,
            'name' => $venue->name,
            'status' => $venue->status,
            'is_active' => $venue->is_active,
        ],

        'owner' => [
            'id' => $owner->id,
            'email' => $owner->email,
            'role' => $owner->role,
        ],
    ], 200);

} catch (\Exception $e) {

    \Log::error('VENUE APPROVAL ERROR', [
        'venue_id' => $id,
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => $e->getFile(),
    ]);

    return response()->json([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}


}

    public function rejectVenue(Request $request, $id)
    {
        try {
            $venue = Venue::findOrFail($id);
            
            $venue->update([
                'is_active' => false,
                'status' => 'rejected',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Venue rejected successfully!',
                'data' => $venue
            ]);
        } catch (\Exception $e) {
            Log::error('rejectVenue error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject venue: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all bookings (admin view)
     */
    public function bookings(Request $request)
    {
        try {
            $query = Booking::with(['user', 'venue']);
            
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }
            
            $bookings = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $bookings
            ]);
        } catch (\Exception $e) {
            Log::error('bookings error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bookings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get booking details
     */
    public function bookingDetail($id)
    {
        try {
            $booking = Booking::with(['user', 'venue', 'venue.owner'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $booking
            ]);
        } catch (\Exception $e) {
            Log::error('bookingDetail error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch booking details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update booking status (admin)
     */
    public function updateBookingStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:pending,confirmed,completed,cancelled'
            ]);

            $booking = Booking::findOrFail($id);
            $booking->status = $request->status;
            $booking->save();

            return response()->json([
                'success' => true,
                'message' => 'Booking status updated successfully',
                'data' => $booking
            ]);
        } catch (\Exception $e) {
            Log::error('updateBookingStatus error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update booking status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard statistics (summary)
     */
    public function statistics()
    {
        try {
            $data = [
                'users' => [
                    'total' => User::count(),
                    'admins' => User::where('role', 'admin')->count(),
                    'owners' => User::where('role', 'owner')->count(),
                    'regular_users' => User::where('role', 'user')->count(),
                ],
                'venues' => [
                    'total' => Venue::count(),
                    'active' => Venue::where('is_active', true)->count(),
                    'pending' => Venue::where('is_active', false)->count(),
                ],
                'bookings' => [
                    'total' => Booking::count(),
                    'pending' => Booking::where('status', 'pending')->count(),
                    'confirmed' => Booking::where('status', 'confirmed')->count(),
                    'completed' => Booking::where('status', 'completed')->count(),
                    'cancelled' => Booking::where('status', 'cancelled')->count(),
                ],
                'recent_bookings' => Booking::with(['user', 'venue'])
                    ->orderBy('created_at', 'desc')
                    ->take(10)
                    ->get(),
                'recent_venues' => Venue::with('owner')
                    ->orderBy('created_at', 'desc')
                    ->take(10)
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('statistics error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics: ' . $e->getMessage()
            ], 500);
        }
    }
}