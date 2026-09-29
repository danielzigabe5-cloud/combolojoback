<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Venue;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    /* ═══════════════════════════════════════════════════════════
       ⚠️ EXISTING METHODS — UNCHANGED
       ነባር ዘዴዎች — ምንም አልተለወጡም
       ═══════════════════════════════════════════════════════════ */

    /**
     * ከFlutter (Mobile) የሚላክ ቡኪንግ መቀበያ
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'venue_id' => 'required|exists:venues,id',
            'user_name' => 'required|string|max:255',
            'phone_number' => 'required|string',
            'start_time' => 'required',
            'end_time' => 'required',
            'sport_type' => 'required|string',
            'payment_method' => 'required|string',
            'total_price' => 'required|numeric',
            'transaction_ref' => 'nullable|string',
            'payment_screenshot' => 'nullable|image|max:2048',
            'special_requests' => 'nullable|string',
            'number_of_players' => 'required|integer|min:1|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $start = Carbon::parse($request->start_time);
            $end = Carbon::parse($request->end_time);

            $existingBooking = Booking::where('venue_id', $request->venue_id)
                ->where('status', '!=', 'rejected')
                ->where(function ($query) use ($start, $end) {
                    $query->whereBetween('start_time', [$start, $end])
                          ->orWhereBetween('end_time', [$start, $end])
                          ->orWhere(function ($q) use ($start, $end) {
                              $q->where('start_time', '<=', $start)
                                ->where('end_time', '>=', $end);
                          });
                })->exists();

            if ($existingBooking) {
                return response()->json([
                    'success' => false,
                    'message' => 'This time slot is already booked. Please choose another time.'
                ], 409);
            }

            $path = null;
            if ($request->hasFile('payment_screenshot')) {
                $path = $request->file('payment_screenshot')->store('payments', 'public');
            }

            $booking = Booking::create([
                'user_id' => Auth::id(),
                'venue_id' => $request->venue_id,
                'user_name' => $request->user_name,
                'phone_number' => $request->phone_number,
                'start_time' => $start,
                'end_time' => $end,
                'sport_type' => $request->sport_type,
                'payment_method' => $request->payment_method,
                'transaction_ref' => $request->transaction_ref,
                'payment_screenshot' => $path,
                'total_price' => $request->total_price,
                'special_requests' => $request->special_requests,
                'number_of_players' => $request->number_of_players ?? 1,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Booking request sent successfully!',
                'booking' => $booking->load('venue', 'user'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ የቦታ መኖር ለማረጋገጥ
     */
    public function checkAvailability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'venue_id' => 'required|exists:venues,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'available' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $start = Carbon::parse($request->start_time);
            $end = Carbon::parse($request->end_time);

            $existingBooking = Booking::where('venue_id', $request->venue_id)
                ->where('status', '!=', 'rejected')
                ->where(function ($query) use ($start, $end) {
                    $query->whereBetween('start_time', [$start, $end])
                          ->orWhereBetween('end_time', [$start, $end])
                          ->orWhere(function ($q) use ($start, $end) {
                              $q->where('start_time', '<=', $start)
                                ->where('end_time', '>=', $end);
                          });
                })->exists();

            if ($existingBooking) {
                return response()->json([
                    'available' => false,
                    'message' => '❌ This time slot is already booked. Please choose another time.',
                    'booked_slots' => Booking::where('venue_id', $request->venue_id)
                        ->where('status', '!=', 'rejected')
                        ->where(function ($query) use ($start, $end) {
                            $query->whereBetween('start_time', [$start, $end])
                                  ->orWhereBetween('end_time', [$start, $end])
                                  ->orWhere(function ($q) use ($start, $end) {
                                      $q->where('start_time', '<=', $start)
                                        ->where('end_time', '>=', $end);
                                  });
                        })
                        ->select('start_time', 'end_time', 'user_name')
                        ->get()
                ]);
            }

            return response()->json([
                'available' => true,
                'message' => '✅ Time slot is available!',
                'venue' => Venue::find($request->venue_id)->only(['id', 'name', 'price_per_hour']),
                'start_time' => $start->format('Y-m-d H:i:s'),
                'end_time' => $end->format('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'available' => false,
                'message' => 'Error checking availability: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ለNuxt Admin ሁሉንም ቡኪንግ ማሳያ (Latest first)
     */
    public function adminIndex()
    {
        return Booking::with(['user', 'venue'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * ከNuxt Admin ቡኪንግ ማረጋገጫ (Confirm)
     */
    public function confirm($id)
    {
        try {
            $booking = Booking::findOrFail($id);

            $conflict = Booking::where('venue_id', $booking->venue_id)
                ->where('id', '!=', $booking->id)
                ->where('status', 'confirmed')
                ->where(function ($query) use ($booking) {
                    $query->whereBetween('start_time', [$booking->start_time, $booking->end_time])
                          ->orWhereBetween('end_time', [$booking->start_time, $booking->end_time])
                          ->orWhere(function ($q) use ($booking) {
                              $q->where('start_time', '<=', $booking->start_time)
                                ->where('end_time', '>=', $booking->end_time);
                          });
                })->exists();

            if ($conflict) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot confirm! Another booking exists for this time slot.'
                ], 409);
            }

            $booking->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Booking confirmed successfully!',
                'booking' => $booking->load('venue', 'user')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ቡኪንግ ውድቅ ለማድረግ
     */
    public function reject($id)
    {
        try {
            $booking = Booking::findOrFail($id);
            $booking->update([
                'status' => 'rejected'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Booking has been rejected.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting booking: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ለሞባይል ተጠቃሚ የራሱን ቡኪንግ ማሳያ
     */
    public function myBookings()
    {
        $userId = Auth::id();

        \Log::info('myBookings called', [
            'user_id'    => $userId,
            'auth_check' => Auth::check(),
            'auth_email' => Auth::user()?->email,
            'header'     => request()->header('Authorization'),
        ]);

        if (!$userId) {
            return response()->json([
                'success' => false,
                'error'   => 'Unauthenticated',
                'message' => 'Token missing or invalid. Please login again.'
            ], 401);
        }

        $bookings = Booking::with(['venue'])
            ->where('user_id', $userId)
            ->latest()
            ->get();

        return response()->json([
            'success'  => true,
            'count'    => $bookings->count(),
            'bookings' => $bookings,
        ]);
    }

    /**
     * ዝርዝር መረጃ ማሳያ
     */
    public function show($id)
    {
        $booking = Booking::with(['venue', 'user'])->findOrFail($id);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if ($booking->user_id != $user->id && $user->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($booking);
    }

    /**
     * ✅ ነፃ የሆኑ ጊዜ ክፍተቶችን ለማምጣት
     */
    public function getAvailableTimeSlots(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'venue_id' => 'required|exists:venues,id',
            'date' => 'required|date',
            'duration' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $date = Carbon::parse($request->date);
            $duration = (float) $request->duration;

            $bookedSlots = Booking::where('venue_id', $request->venue_id)
                ->where('status', '!=', 'rejected')
                ->whereDate('start_time', $date)
                ->select('start_time', 'end_time')
                ->get();

            $startHour = 8;
            $endHour = 22;
            $availableSlots = [];

            for ($hour = $startHour; $hour < $endHour; $hour++) {
                $slotStart = Carbon::parse($date->format('Y-m-d') . " $hour:00:00");
                $slotEnd = Carbon::parse($date->format('Y-m-d') . " " . ($hour + $duration) . ":00:00");

                if ($slotEnd->hour > $endHour) {
                    break;
                }

                $isBooked = false;
                foreach ($bookedSlots as $booked) {
                    $bookedStart = Carbon::parse($booked->start_time);
                    $bookedEnd = Carbon::parse($booked->end_time);

                    if ($slotStart->lt($bookedEnd) && $slotEnd->gt($bookedStart)) {
                        $isBooked = true;
                        break;
                    }
                }

                if (!$isBooked) {
                    $availableSlots[] = [
                        'start_time' => $slotStart->format('h:i A'),
                        'end_time' => $slotEnd->format('h:i A'),
                        'start_hour' => $slotStart->hour,
                        'start_minute' => $slotStart->minute,
                        'end_hour' => $slotEnd->hour,
                        'end_minute' => $slotEnd->minute,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'slots' => $availableSlots,
                'total' => count($availableSlots),
                'date' => $date->format('Y-m-d'),
                'duration' => $duration,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       🆕 NEW METHODS — PARTNER BOOKINGS
       አዲስ ዘዴዎች — ለአጋር (Partner) ብቻ
       
       🔒 የደንበኛ ስም ብቻ ይላካል — ስልክ, ኢሜይል አይላክም
       ═══════════════════════════════════════════════════════════ */

    /**
     * 🎯 የአጋሩን ቦታ ማስያዣዎች ያመጣል
     * 
     * 🔒 የደንበኛ ስም ብቻ ይመልሳል
     * ❌ ስልክ ቁጥር — አይላክም
     * ❌ ኢሜይል — አይላክም
     */
    public function partnerBookings(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 401);
            }

            // 🎯 የአጋሩን ሜዳዎች ID ብቻ አምጣ
            $venueIds = Venue::where('owner_id', $user->id)
                ->orWhere('user_id', $user->id)
                ->pluck('id');

            if ($venueIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data'    => [],
                ]);
            }

            // 🎯 Filters
            $status  = $request->query('status', 'All');
            $date    = $request->query('date');
            $venueId = $request->query('venue_id');

            $query = Booking::with([
                    'user:id,name',   // 🔑 ስም ብቻ — ስልክ አይደለም!
                    'venue:id,name',  // 🔑 የሜዳ ስም ብቻ
                ])
                ->whereIn('venue_id', $venueIds)
                ->latest();

            if ($status && $status !== 'All') {
                $query->where('status', strtolower($status));
            }

            if ($date) {
                $query->whereDate('start_time', $date);
            }

            if ($venueId) {
                $query->where('venue_id', $venueId);
            }

            $bookings = $query->get()->map(function ($booking) {
                return [
                    'id'         => $booking->id,
                    'customer'   => $booking->user?->name ?? $booking->user_name ?? 'Customer',
                    // ❌ 'phone' — ጨርሶ አይላክም!
                    'venue_id'   => $booking->venue_id,
                    'venue_name' => $booking->venue?->name ?? 'Unknown',
                    'date'       => $booking->start_time?->format('M d, Y') ?? '—',
                    'date_raw'   => $booking->start_time?->format('Y-m-d') ?? null,
                    'time'       => $this->formatTimeRange($booking),
                    'amount'     => number_format($booking->total_price ?? 0),
                    'status'     => ucfirst($booking->status),
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $bookings,
            ]);

        } catch (\Exception $e) {
            \Log::error('partnerBookings error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load bookings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 🎯 የአጋሩ የስታቲስቲክስ ዳታ
     * 
     * ይመልሳል: total, confirmed, pending, cancelled, today, venues
     */
    public function partnerBookingStats(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 401);
            }

            $venueIds = Venue::where('owner_id', $user->id)
                ->orWhere('user_id', $user->id)
                ->pluck('id');

            if ($venueIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data'    => [
                        'total'     => 0,
                        'confirmed' => 0,
                        'pending'   => 0,
                        'cancelled' => 0,
                        'today'     => 0,
                        'venues'    => 0,
                    ],
                ]);
            }

            $today = Carbon::today()->toDateString();

            $stats = Booking::whereIn('venue_id', $venueIds)
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'rejected' OR status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(CASE WHEN DATE(start_time) = ? THEN 1 ELSE 0 END) as today
                ", [$today])
                ->first();

            return response()->json([
                'success' => true,
                'data'    => [
                    'total'     => (int) ($stats->total ?? 0),
                    'confirmed' => (int) ($stats->confirmed ?? 0),
                    'pending'   => (int) ($stats->pending ?? 0),
                    'cancelled' => (int) ($stats->cancelled ?? 0),
                    'today'     => (int) ($stats->today ?? 0),
                    'venues'    => $venueIds->count(),
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('partnerBookingStats error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load stats.',
            ], 500);
        }
    }

    /**
     * 🎯 የቦታ ማስያዣ ማረጋገጫ (Partner)
     */
    public function confirmPartnerBooking($id)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 401);
            }

            // የአጋሩን ሜዳዎች ID ብቻ
            $venueIds = Venue::where('owner_id', $user->id)
                ->orWhere('user_id', $user->id)
                ->pluck('id');

            $booking = Booking::whereIn('venue_id', $venueIds)->find($id);

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found.',
                ], 404);
            }

            $booking->status = 'confirmed';
            $booking->confirmed_at = now();
            $booking->save();

            return response()->json([
                'success' => true,
                'message' => 'Booking confirmed.',
                'data'    => [
                    'id'     => $booking->id,
                    'status' => 'Confirmed',
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('confirmPartnerBooking error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm booking.',
            ], 500);
        }
    }

    /**
     * 🎯 የቦታ ማስያዣ ስረዛ (Partner)
     */
    public function rejectPartnerBooking($id)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 401);
            }

            $venueIds = Venue::where('owner_id', $user->id)
                ->orWhere('user_id', $user->id)
                ->pluck('id');

            $booking = Booking::whereIn('venue_id', $venueIds)->find($id);

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found.',
                ], 404);
            }

            $booking->status = 'rejected';
            $booking->save();

            return response()->json([
                'success' => true,
                'message' => 'Booking cancelled.',
                'data'    => [
                    'id'     => $booking->id,
                    'status' => 'Rejected',
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('rejectPartnerBooking error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel booking.',
            ], 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       🆕 HELPER
       ═══════════════════════════════════════════════════════════ */

    /**
     * የጊዜ ክልል ፎርማት (4:00 PM - 5:00 PM)
     */
    private function formatTimeRange($booking): string
    {
        if (!$booking->start_time) return '—';

        $start = Carbon::parse($booking->start_time)->format('g:i A');
        $end   = $booking->end_time
            ? Carbon::parse($booking->end_time)->format('g:i A')
            : null;

        return $end ? "{$start} - {$end}" : $start;
    }
}