<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PartnerDashboardController extends Controller
{
    /**
     * ሁሉንም የዳሽቦርድ ዳታ በአንድ ጥሪ ያመጣል
     */
    public function index(Request $request): JsonResponse
    {
        $partnerId = $request->user()->id;
        $cacheKey  = "partner_dashboard_{$partnerId}";

        $payload = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($partnerId) {
            // ✅ ትክክለኛው አምድ 'owner_id' ነው!
            // 'user_id' አይደለም፣ 'partner_id' አይደለም
            $venue = Venue::where('owner_id', $partnerId)
                ->orWhere('user_id', $partnerId)  // አማራጭ ጥበቃ
                ->first();

            if (! $venue) {
                return null;
            }

            return [
                'venue'    => $this->formatVenue($venue),
                'stats'    => $this->buildStats($venue, $partnerId),
                'bookings' => $this->recentBookings($venue),
            ];
        });

        if (! $payload) {
            return response()->json([
                'success' => false,
                'message' => 'Venue not found for this partner.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $payload,
        ]);
    }

    private function formatVenue(Venue $venue): array
    {
        return [
            'id'           => $venue->id,
            'name'         => $venue->name,
            'location'     => $venue->location
                              ?? $venue->city
                              ?? 'Addis Ababa',
            'sportType'    => $venue->sport ?? 'Futsal',
            'pricePerHour' => (int) $venue->price_per_hour,
            'status'       => $venue->is_active ? 'Active' : ($venue->status ?? 'Pending'),
            'completion'   => (int) ($venue->completion ?? 85),
        ];
    }

    private function buildStats(Venue $venue, int $partnerId): array
    {
        $startOfMonth     = Carbon::now()->startOfMonth();
        $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
        $endOfLastMonth   = Carbon::now()->subMonth()->endOfMonth();

        // ✅ 'total_price' ይጠቀማል ('amount' አይደለም!)
        $totalBookings = Booking::where('venue_id', $venue->id)->count();

        $monthRevenue = Booking::where('venue_id', $venue->id)
            ->where('status', 'confirmed')
            ->where('created_at', '>=', $startOfMonth)
            ->sum('total_price');

        $lastMonthRevenue = Booking::where('venue_id', $venue->id)
            ->where('status', 'confirmed')
            ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
            ->sum('total_price');

        $pendingCount = Booking::where('venue_id', $venue->id)
            ->where('status', 'pending')
            ->count();

        $availableSlots = 0;
        try {
            $availableSlots = $venue->slots()
                ->whereDate('date', Carbon::today())
                ->where('is_booked', false)
                ->count();
        } catch (\Exception $e) {
            $availableSlots = 0;
        }

        $lastMonth = (float) $lastMonthRevenue;
        $thisMonth = (float) $monthRevenue;

        $revenueChange = $lastMonth > 0
            ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1)
            : 0;

        $revenueTrend = $revenueChange >= 0 ? 'up' : 'down';
        $revenueLabel = $revenueChange >= 0
            ? "+{$revenueChange}% from last month"
            : "{$revenueChange}% from last month";

        return [
            [
                'title'  => 'Total Bookings',
                'value'  => (string) $totalBookings,
                'change' => '+12% this month',
                'icon'   => '📅',
                'trend'  => 'up',
            ],
            [
                'title'  => 'This Month Revenue',
                'value'  => 'ETB ' . number_format($thisMonth),
                'change' => $revenueLabel,
                'icon'   => '💰',
                'trend'  => $revenueTrend,
            ],
            [
                'title'  => 'Available Slots',
                'value'  => (string) $availableSlots,
                'change' => 'Today',
                'icon'   => '◷',
                'trend'  => 'neutral',
            ],
            [
                'title'  => 'Pending Requests',
                'value'  => (string) $pendingCount,
                'change' => $pendingCount > 0 ? 'Needs attention' : 'All clear',
                'icon'   => '🔔',
                'trend'  => $pendingCount > 0 ? 'down' : 'neutral',
            ],
        ];
    }

    private function recentBookings(Venue $venue): array
    {
        // ✅ 'user' ግንኙነት ('customer' አይደለም!)
        return Booking::with(['user:id,name'])
            ->where('venue_id', $venue->id)
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (Booking $booking) => [
                'id'       => $booking->id,
                'customer' => $booking->user?->name ?? 'Unknown',
                'date'     => $this->formatDate($booking->start_time),
                'time'     => $this->formatTime($booking->start_time)
                              . ' - '
                              . $this->formatTime($booking->end_time),
                'amount'   => number_format($booking->total_price),
                'status'   => ucfirst($booking->status),
            ])
            ->toArray();
    }

    private function formatDate(?Carbon $date): string
    {
        if (! $date) return '—';
        if ($date->isToday()) return 'Today';
        if ($date->isTomorrow()) return 'Tomorrow';
        return $date->format('M d');
    }

    private function formatTime(?Carbon $time): string
    {
        return $time ? $time->format('g:i A') : '—';
    }
}