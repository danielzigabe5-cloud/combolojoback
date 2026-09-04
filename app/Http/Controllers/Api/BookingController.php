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
            // ✅ በተመሳሳይ ጊዜ ቦታ መኖሩን አረጋግጥ
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

            // ምስል ማስቀመጥ
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

            // ✅ በተመሳሳይ ጊዜ የተያዙ ቦታዎችን ፈልግ
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
            
            // ✅ ሌላ ቡኪንግ በተመሳሳይ ጊዜ እንዳለ አረጋግጥ
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
        $bookings = Booking::with(['venue'])
            ->where('user_id', Auth::id())
            ->latest()
            ->get();
        return response()->json($bookings);
    }

    /**
     * ዝርዝር መረጃ ማሳያ
     */
    public function show($id)
    {
        $booking = Booking::with(['venue', 'user'])->findOrFail($id);
        
        if ($booking->user_id != Auth::id() && Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        return response()->json($booking);
    }
    // app/Http/Controllers/Api/BookingController.php

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
        
        // የተያዙ ጊዜያትን አግኝ
        $bookedSlots = Booking::where('venue_id', $request->venue_id)
            ->where('status', '!=', 'rejected')
            ->whereDate('start_time', $date)
            ->select('start_time', 'end_time')
            ->get();

        // ከ8:00 AM እስከ 10:00 PM ያሉ ጊዜያት
        $startHour = 8;
        $endHour = 22;
        $availableSlots = [];

        for ($hour = $startHour; $hour < $endHour; $hour++) {
            $slotStart = Carbon::parse($date->format('Y-m-d') . " $hour:00:00");
            $slotEnd = Carbon::parse($date->format('Y-m-d') . " " . ($hour + $duration) . ":00:00");

            // ከ10:00 PM በላይ ከሆነ አቁም
            if ($slotEnd->hour > $endHour) {
                break;
            }

            // ይህ ጊዜ ተይዟል?
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
}