<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Models\Booking;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ScheduleController extends Controller
{
    // 1. የፓርትነሩን የሜዳዎች ዝርዝር ያመጣል
    public function getVenues()
    {
        $venues = Venue::where('partner_id', Auth::id())->get(['id', 'name']);
        return response()->json($venues);
    }

    // 2. የተመረጠው ሜዳ በተመረጠው ቀን ያለውን ሁኔታ ያመጣል
    public function getSchedule(Request $request)
    {
        $venueId = $request->venue_id;
        $date = $request->date ?? Carbon::today()->toDateString();

        // ከጠዋት 2 ሰዓት እስከ ማታ 4 ሰዓት (8:00 AM - 10:00 PM)
        $startTime = 8;
        $endTime = 22;
        $slots = [];

        // በዛ ቀን ያሉ ቡኪንጎችን ማምጣት
        $bookings = Booking::with('user')
            ->where('venue_id', $venueId)
            ->whereDate('start_time', $date)
            ->get();

        for ($i = $startTime; $i < $endTime; $i++) {
            $currentHour = Carbon::parse($date)->setHour($i);
            $slotString = $currentHour->format('h:i A') . ' - ' . $currentHour->copy()->addHour()->format('h:i A');
            
            // ቡኪንግ መኖሩን ማረጋገጥ
            $booking = $bookings->first(function ($b) use ($i) {
                return Carbon::parse($b->start_time)->hour == $i;
            });

            $status = 'available';
            if ($booking) {
                $status = ($booking->status == 'blocked') ? 'blocked' : 'booked';
            }

            $slots[] = [
                'id' => $i,
                'time' => $slotString,
                'status' => $status,
                'bookedBy' => $booking ? ($booking->user->name ?? 'N/A') : null,
                'phone' => $booking ? ($booking->user->phone ?? 'N/A') : null,
                'price' => number_format($booking->price ?? 1000) . ' ETB',
                'booking_id' => $booking ? $booking->id : null
            ];
        }

        return response()->json($slots);
    }

    // 3. ሜዳውን መዝጋት ወይም መክፈት (Toggle Block)
    public function toggleBlock(Request $request)
    {
        $venueId = $request->venue_id;
        $date = $request->date;
        $hour = $request->hour;

        $startTime = Carbon::parse($date)->setHour($hour)->startOfHour();
        $endTime = $startTime->copy()->addHour();

        $existing = Booking::where('venue_id', $venueId)
            ->where('start_time', $startTime)
            ->first();

        if ($existing) {
            if ($existing->status == 'blocked') {
                $existing->delete(); // ይከፈታል
                return response()->json(['message' => 'ሰዓቱ ክፍት ሆኗል']);
            }
            return response()->json(['message' => 'ይህ ሰዓት ቀድሞውኑ በሰው ተይዟል'], 400);
        }

        // አዲስ ብሎክ ማድረግ
        Booking::create([
            'venue_id' => $venueId,
            'user_id' => Auth::id(), // ወይም ሲስተም ዩዘር
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => 'blocked',
            'price' => 0
        ]);

        return response()->json(['message' => 'ሰዓቱ ተዘግቷል']);
    }
}