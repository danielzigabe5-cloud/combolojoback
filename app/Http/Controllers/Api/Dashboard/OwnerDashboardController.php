<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class OwnerDashboardController extends Controller
{
    public function getOverview()
    {
        $partnerId = Auth::id();

        // 1. የሜዳዎች ብዛት
        $venueCount = Venue::where('partner_id', $partnerId)->count();

        // 2. ጠቅላላ ገቢ - confirmed bookings only
        $totalEarnings = Booking::whereHas('venue', function ($query) use ($partnerId) {
            $query->where('partner_id', $partnerId);
        })
        ->where('status', 'confirmed')
        ->sum('total_price');

        // 3. የዚህ ወር ቡኪንግ ብዛት
        $monthlyBookings = Booking::whereHas('venue', function ($query) use ($partnerId) {
            $query->where('partner_id', $partnerId);
        })
        ->whereMonth('created_at', Carbon::now()->month)
        ->whereYear('created_at', Carbon::now()->year)
        ->count();

        // 4. Pending payments
        $pendingPayments = Booking::whereHas('venue', function ($query) use ($partnerId) {
            $query->where('partner_id', $partnerId);
        })
        ->where('status', 'pending')
        ->sum('total_price');

        // 5. የቅርብ ጊዜ 5 ቡኪንጎች
        $recentBookings = Booking::with(['user', 'venue'])
            ->whereHas('venue', function ($query) use ($partnerId) {
                $query->where('partner_id', $partnerId);
            })
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($booking) {

                return [
                    'id' => '#BK-' . $booking->id,

                    'customer' => $booking->user
                        ? $booking->user->name
                        : 'Unknown',

                    'venue' => $booking->venue
                        ? $booking->venue->name
                        : 'Unknown',

                    'date' => $booking->start_time
                        ? $booking->start_time->format('Y-m-d')
                        : null,

                    'time' => $booking->start_time && $booking->end_time
                        ? $booking->start_time->format('h:i A')
                            . ' - ' .
                            $booking->end_time->format('h:i A')
                        : null,

                    'amount' => 'ብር ' . number_format($booking->total_price),

                    'status' => $this->getStatusAmharic(
                        $booking->status
                    ),

                    'statusColor' => $this->getStatusColor(
                        $booking->status
                    ),
                ];
            });

        return response()->json([
            'stats' => [
                'earnings' => 'ብር ' . number_format($totalEarnings),
                'venues' => $venueCount,
                'bookings' => $monthlyBookings,
                'pending' => 'ብር ' . number_format($pendingPayments),
            ],

            'recentBookings' => $recentBookings
        ]);
    }


    private function getStatusAmharic($status)
    {
        return [
            'confirmed' => 'የተረጋገጠ',
            'pending' => 'የሚጠበቅ',
            'cancelled' => 'የተሰረዘ',
        ][$status] ?? $status;
    }


    private function getStatusColor($status)
    {
        return [
            'confirmed' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
            'pending' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
            'cancelled' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
        ][$status] ?? '';
    }
}

