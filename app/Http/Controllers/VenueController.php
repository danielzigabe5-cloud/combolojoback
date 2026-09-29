<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Models\VenueSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class VenueController extends Controller
{
    /* ═══════════════════════════════════════════════════════════
       EXISTING METHODS
       ═══════════════════════════════════════════════════════════ */

    public function index()
    {
        try {
            $venues = Venue::with('user')
                ->where('is_active', true)
                ->where('status', 'approved')
                ->orderBy('created_at', 'desc')
                ->get();

            $venues->each(function ($venue) {
                $venue->image_full_url = $venue->image
                    ? asset('storage/' . $venue->image)
                    : null;
            });

            return response()->json([
                'success' => true,
                'data'    => $venues,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching venues: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch venues',
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $venue = Venue::with('user')
                ->where('is_active', true)
                ->where('status', 'approved')
                ->find($id);

            if (!$venue) {
                return response()->json([
                    'success' => false,
                    'message' => 'Venue not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data'    => $venue,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching venue: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch venue',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Please login first.',
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'name'           => 'required|string|min:3|max:255',
                'description'    => 'nullable|string|max:1000',
                'location'       => 'required|string|max:255',
                'city'           => 'required|string|max:255',
                'sub_city'       => 'nullable|string|max:255',
                'capacity'       => 'required|integer|min:1',
                'price_per_hour' => 'required|numeric|min:0',
                'image'          => 'required|image|mimes:jpeg,png,jpg|max:2048',
                'sport_types'    => 'nullable|string',
                'facilities'     => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed: ' . $validator->errors()->first(),
                ], 422);
            }

            $isPartnerOrAdmin = in_array($user->role, ['partner', 'admin', 'owner']);
            $status  = $isPartnerOrAdmin ? 'approved' : 'pending';
            $isActive = $isPartnerOrAdmin ? true : false;

            DB::beginTransaction();

            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('venues', 'public');
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Image is required',
                ], 422);
            }

            $sportTypes = $request->sport_types ? json_decode($request->sport_types, true) : [];
            $facilities = $request->facilities ? json_decode($request->facilities, true) : [];

            $venue = Venue::create([
                'owner_id'       => $user->id,
                'user_id'        => $user->id,
                'name'           => $request->name,
                'description'    => $request->description,
                'location'       => $request->location,
                'city'           => $request->city,
                'sub_city'       => $request->sub_city,
                'capacity'       => (int) $request->capacity,
                'price_per_hour' => (float) $request->price_per_hour,
                'image'          => $imagePath,
                'sport_types'    => $sportTypes,
                'facilities'     => $facilities,
                'is_active'      => $isActive,
                'status'         => $status,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $isPartnerOrAdmin
                    ? 'Venue registered successfully!'
                    : 'Venue registered successfully! Please wait for admin approval.',
                'data'    => $venue,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Venue registration error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to register venue: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function myVenues(Request $request)
    {
        try {
            $user = $request->user();
            $venues = Venue::with(['user', 'schedules'])
                ->where('owner_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            $venues->each(function ($venue) {
                $venue->image_full_url = $venue->image
                    ? asset('storage/' . $venue->image)
                    : null;
            });

            return response()->json([
                'success' => true,
                'data'    => $venues,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching my venues: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch venues',
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 401);
            }

            $venue = Venue::where('owner_id', $user->id)->find($id);

            if (!$venue && $user->role === 'admin') {
                $venue = Venue::find($id);
            }

            if (!$venue) {
                return response()->json([
                    'success' => false,
                    'message' => 'Venue not found or not yours.',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name'           => 'sometimes|required|string|min:3|max:255',
                'description'    => 'nullable|string|max:1000',
                'location'       => 'sometimes|required|string|max:255',
                'city'           => 'nullable|string|max:255',
                'sub_city'       => 'nullable|string|max:255',
                'address'        => 'nullable|string|max:255',
                'capacity'       => 'sometimes|required|integer|min:1',
                'price_per_hour' => 'sometimes|required|numeric|min:0',
                'sport'          => 'nullable|string|max:100',
                'opening_time'   => 'nullable|string|max:10',
                'closing_time'   => 'nullable|string|max:10',
                'is_active'      => 'nullable|boolean',
                'image'          => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed: ' . $validator->errors()->first(),
                ], 422);
            }

            DB::beginTransaction();

            if ($request->hasFile('image')) {
                if ($venue->image && Storage::disk('public')->exists($venue->image)) {
                    Storage::disk('public')->delete($venue->image);
                }
                $venue->image = $request->file('image')->store('venues', 'public');
            }

            $fillable = [
                'name', 'description', 'location', 'city', 'sub_city',
                'address', 'capacity', 'price_per_hour', 'sport',
                'opening_time', 'closing_time', 'is_active',
            ];

            foreach ($fillable as $field) {
                if ($request->has($field)) {
                    $venue->{$field} = $request->input($field);
                }
            }

            $venue->save();
            DB::commit();

            $venue->image_full_url = $venue->image
                ? asset('storage/' . $venue->image)
                : null;

            return response()->json([
                'success' => true,
                'message' => 'Venue updated successfully!',
                'data'    => $venue->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Venue update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update venue: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 401);
            }

            $venue = Venue::where('owner_id', $user->id)->find($id);

            if (!$venue && $user->role === 'admin') {
                $venue = Venue::find($id);
            }

            if (!$venue) {
                return response()->json([
                    'success' => false,
                    'message' => 'Venue not found or not yours.',
                ], 404);
            }

            DB::beginTransaction();

            if ($venue->image && Storage::disk('public')->exists($venue->image)) {
                Storage::disk('public')->delete($venue->image);
            }

            if (method_exists($venue, 'schedules')) {
                $venue->schedules()->delete();
            }

            $venue->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Venue deleted successfully.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Venue delete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete venue: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       🎯 SCHEDULE METHODS — የተሟሉ
       ═══════════════════════════════════════════════════════════ */

    /**
     * የሜዳውን የቀን ሰዓታት ይመልሳል
     */
    public function getSchedule(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();

            $venue = Venue::where('id', $id)
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->orWhere('owner_id', $user->id);
                })
                ->first();

            if (! $venue) {
                return response()->json([
                    'success' => false,
                    'message' => 'Venue not found or access denied.',
                ], 404);
            }

            $date = $request->query('date', now()->toDateString());
            $carbonDate = \Carbon\Carbon::parse($date);
            $dayOfWeek = $carbonDate->dayOfWeek; // 0 = Sunday, 6 = Saturday

            // 🎯 1. የሳምንቱ መርሃ ግብር
            $weeklySchedule = VenueSchedule::where('venue_id', $venue->id)
                ->where('day_of_week', $dayOfWeek)
                ->whereNull('date')
                ->first();

            // 🎯 2. የቀኑ የተዘጉ ሰዓታት
            $daySchedules = VenueSchedule::where('venue_id', $venue->id)
                ->whereDate('date', $date)
                ->whereNotNull('start_time')
                ->get()
                ->keyBy(function ($item) {
                    return \Carbon\Carbon::parse($item->start_time)->format('H:i');
                });

            // 🎯 3. የመክፈቻ እና የመዝጊያ ሰዓታት
            $openTime  = '06:00';
            $closeTime = '22:00';

            if ($weeklySchedule && ! $weeklySchedule->is_closed) {
                $openTime  = $weeklySchedule->open_time ?? $openTime;
                $closeTime = $weeklySchedule->close_time ?? $closeTime;
            } elseif ($venue->opening_time && $venue->closing_time) {
                $openTime  = $venue->opening_time;
                $closeTime = $venue->closing_time;
            }

            // 🎯 4. ሰዓታትን ፍጠር
            $openHour  = (int) explode(':', $openTime)[0];
            $closeHour = (int) explode(':', $closeTime)[0];

            $slots = [];
            for ($hour = $openHour; $hour < $closeHour; $hour++) {
                $timeStr = sprintf('%02d:00', $hour);
                $schedule = $daySchedules->get($timeStr);

                $status = 'available';
                if ($schedule && $schedule->is_booked) {
                    $status = 'blocked';
                }

                $bookingData = $this->getBookingForSlot($venue->id, $date, $timeStr);

                $slots[] = [
                    'id'            => $hour,
                    'time'          => \Carbon\Carbon::parse($timeStr)->format('h:i A'),
                    'status'        => $bookingData ? 'booked' : $status,
                    'price'         => 'ETB ' . number_format($venue->price_per_hour),
                    'bookedBy'      => $bookingData['bookedBy'] ?? null,
                    'phone'         => $bookingData['phone'] ?? null,
                    'paymentStatus' => $bookingData['paymentStatus'] ?? null,
                ];
            }

            return response()->json($slots);

        } catch (\Exception $e) {
            Log::error('Error in getSchedule: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load schedule: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * የሳምንቱን መርሃ ግብር ያስቀምጣል
     */
    public function saveSchedule(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();

            $venue = Venue::where('id', $id)
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->orWhere('owner_id', $user->id);
                })
                ->first();

            if (! $venue) {
                return response()->json([
                    'success' => false,
                    'message' => 'Venue not found or access denied.',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'schedule' => 'required|array|min:1',
                'schedule.*.day_of_week' => 'required|integer|between:0,6',
                'schedule.*.open_time'   => 'required|string',
                'schedule.*.close_time'  => 'required|string',
                'schedule.*.is_closed'   => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed: ' . $validator->errors()->first(),
                ], 422);
            }

            DB::beginTransaction();

            // አሮጌውን የሳምንት መርሃ ግብር ሰርዝ (date NULL የሆነውን)
            VenueSchedule::where('venue_id', $venue->id)
                ->whereNull('date')
                ->delete();

            // አዲሱን አስገባ
            foreach ($request->input('schedule', []) as $slot) {
                VenueSchedule::create([
                    'venue_id'    => $venue->id,
                    'date'        => null,
                    'day_of_week' => (int) $slot['day_of_week'],
                    'open_time'   => $slot['open_time'],
                    'close_time'  => $slot['close_time'],
                    'start_time'  => null,
                    'end_time'    => null,
                    'is_closed'   => (bool) ($slot['is_closed'] ?? false),
                    'is_booked'   => false,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Schedule saved successfully.',
                'data'    => VenueSchedule::where('venue_id', $venue->id)
                    ->whereNull('date')
                    ->orderBy('day_of_week')
                    ->get(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving schedule: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to save schedule: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * የአንድን ሰዓት መክፈት/መዝጋት
     */
    public function toggleBlock(Request $request, $id): JsonResponse
{
    try {
        $user = $request->user();

        $venue = Venue::where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('owner_id', $user->id);
            })
            ->first();

        if (! $venue) {
            return response()->json([
                'success' => false,
                'message' => 'Venue not found or access denied.',
            ], 404);
        }

        $data = $request->validate([
            'date' => 'required|date',
            'hour' => 'required|integer|min:0|max:23',
        ]);

        $timeStr = sprintf('%02d:00', $data['hour']);
        $endStr  = sprintf('%02d:00', $data['hour'] + 1);

        // ነባር መዝገብ ፈልግ
        $schedule = VenueSchedule::where('venue_id', $venue->id)
            ->whereDate('date', $data['date'])
            ->where('start_time', $timeStr)
            ->first();

        if ($schedule) {
            $schedule->is_booked = ! $schedule->is_booked;
            $schedule->save();
            $newStatus = $schedule->is_booked ? 'blocked' : 'available';
        } else {
            // 🎯 አዲስ ፍጠር — NOT NULL አምዶችን መሙላት የግድ ነው!
            $schedule = VenueSchedule::create([
                'venue_id'    => $venue->id,
                'date'        => $data['date'],
                'day_of_week' => 0,
                'open_time'   => $timeStr,      // 🔑 አስቀምጥ
                'close_time'  => $endStr,       // 🔑 አስቀምጥ
                'start_time'  => $timeStr,
                'end_time'    => $endStr,
                'is_closed'   => false,
                'is_booked'   => true,
            ]);
            $newStatus = 'blocked';
        }

        return response()->json([
            'success' => true,
            'message' => 'Slot status updated.',
            'data'    => [
                'id'        => $data['hour'],
                'status'    => $newStatus,
                'is_booked' => $schedule->is_booked,
            ],
        ]);

    } catch (\Exception $e) {
        Log::error('Toggle block error: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to toggle slot: ' . $e->getMessage(),
        ], 500);
    }
}
    /**
     * ከቦታ ማስያዣ ሠንጠረዥ የደንበኛ ዳታ ያወጣል
     */
    private function getBookingForSlot(int $venueId, string $date, string $startTime): ?array
    {
        try {
            if (! class_exists(\App\Models\Booking::class)) {
                return null;
            }

            $booking = \App\Models\Booking::where('venue_id', $venueId)
                ->whereDate('start_time', $date)
                ->whereTime('start_time', $startTime)
                ->whereIn('status', ['confirmed', 'pending'])
                ->with('user:id,name,phone_number')
                ->first();

            if ($booking) {
                return [
                    'bookedBy'      => $booking->user?->name ?? 'Customer',
                    'phone'         => $booking->user?->phone_number ?? null,
                    'paymentStatus' => $booking->status === 'confirmed' ? 'Paid' : 'Pending',
                ];
            }
        } catch (\Exception $e) {
            Log::warning('getBookingForSlot failed: ' . $e->getMessage());
        }

        return null;
    }
}