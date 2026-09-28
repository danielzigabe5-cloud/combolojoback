<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Models\VenueSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class VenueController extends Controller
{
    /* ═══════════════════════════════════════════════════════════
       EXISTING METHODS — UNCHANGED
       ═══════════════════════════════════════════════════════════ */

    public function index()
    {
        try {
            $venues = Venue::with('user')
                ->where('is_active', true)
                ->where('status', 'approved')
                ->orderBy('created_at', 'desc')
                ->get();

            // ✅ ለእያንዳንዱ ቬኒ ሙሉ የምስል URL ያክሉ
            $venues->each(function ($venue) {
                if ($venue->image) {
                    $venue->image_full_url = asset('storage/' . $venue->image);
                } else {
                    $venue->image_full_url = null;
                }
            });

            return response()->json([
                'success' => true,
                'data' => $venues
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Error fetching venues: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch venues'
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
                    'message' => 'Venue not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $venue
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Error fetching venue: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch venue'
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
                    'message' => 'Unauthorized. Please login first.'
                ], 401);
            }

            // ✅ Validation
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|min:3|max:255',
                'description' => 'nullable|string|max:1000',
                'location' => 'required|string|max:255',
                'city' => 'required|string|max:255',
                'sub_city' => 'nullable|string|max:255',
                'capacity' => 'required|integer|min:1',
                'price_per_hour' => 'required|numeric|min:0',
                'image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
                'sport_types' => 'nullable|string',
                'facilities' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed: ' . $validator->errors()->first()
                ], 422);
            }

            $isPartnerOrAdmin = in_array($user->role, ['partner', 'admin']);
            $status = $isPartnerOrAdmin ? 'approved' : 'pending';
            $isActive = $isPartnerOrAdmin ? true : false;

            DB::beginTransaction();

            // ✅ ምስል ማስቀመጫ
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('venues', 'public');
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Image is required'
                ], 422);
            }

            // ✅ Sport types እና Facilities
            $sportTypes = $request->sport_types ? json_decode($request->sport_types, true) : [];
            $facilities = $request->facilities ? json_decode($request->facilities, true) : [];

            // ✅ ቬኒ መፍጠር
            $venue = Venue::create([
                'owner_id' => $user->id,
                'user_id' => $user->id,
                'name' => $request->name,
                'description' => $request->description,
                'location' => $request->location,
                'city' => $request->city,
                'sub_city' => $request->sub_city,
                'capacity' => (int) $request->capacity,
                'price_per_hour' => (float) $request->price_per_hour,
                'image' => $imagePath,
                'sport_types' => $sportTypes,
                'facilities' => $facilities,
                'is_active' => $isActive,
                'status' => $status,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $isPartnerOrAdmin 
                    ? 'Venue registered successfully!' 
                    : 'Venue registered successfully! Please wait for admin approval.',
                'data' => $venue
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Venue registration error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to register venue: ' . $e->getMessage()
            ], 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       UPDATED myVenues — now includes schedule + image_full_url
       ═══════════════════════════════════════════════════════════ */
    public function myVenues(Request $request)
    {
        try {
            $user = $request->user();
            $venues = Venue::with(['user', 'schedules'])
                ->where('owner_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            // ✅ ሙሉ የምስል URL ለእያንዳንዱ ቬኒ
            $venues->each(function ($venue) {
                $venue->image_full_url = $venue->image
                    ? asset('storage/' . $venue->image)
                    : null;
            });

            return response()->json([
                'success' => true,
                'data' => $venues
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching my venues: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch venues'
            ], 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       NEW — UPDATE a venue (owner or admin only)
       ═══════════════════════════════════════════════════════════ */
    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.'
                ], 401);
            }

            $venue = Venue::where('owner_id', $user->id)->find($id);

            // Allow admin too
            if (!$venue && $user->role === 'admin') {
                $venue = Venue::find($id);
            }

            if (!$venue) {
                return response()->json([
                    'success' => false,
                    'message' => 'Venue not found or not yours.'
                ], 404);
            }

            // ✅ Validation
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|min:3|max:255',
                'description' => 'nullable|string|max:1000',
                'location' => 'sometimes|required|string|max:255',
                'city' => 'nullable|string|max:255',
                'sub_city' => 'nullable|string|max:255',
                'address' => 'nullable|string|max:255',
                'capacity' => 'sometimes|required|integer|min:1',
                'price_per_hour' => 'sometimes|required|numeric|min:0',
                'sport' => 'nullable|string|max:100',
                'opening_time' => 'nullable|string|max:10',
                'closing_time' => 'nullable|string|max:10',
                'is_active' => 'nullable|boolean',
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed: ' . $validator->errors()->first()
                ], 422);
            }

            DB::beginTransaction();

            // ✅ አዲስ ምስል ካለ ብቻ ይተካ
            if ($request->hasFile('image')) {
                if ($venue->image && Storage::disk('public')->exists($venue->image)) {
                    Storage::disk('public')->delete($venue->image);
                }
                $venue->image = $request->file('image')->store('venues', 'public');
            }

            // ✅ የተለዋዋጭ መስኮችን ብቻ አዘምን
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

            // ✅ ሙሉ የምስል URL
            $venue->image_full_url = $venue->image
                ? asset('storage/' . $venue->image)
                : null;

            return response()->json([
                'success' => true,
                'message' => 'Venue updated successfully!',
                'data' => $venue->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Venue update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update venue: ' . $e->getMessage()
            ], 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       NEW — DELETE a venue (owner or admin only)
       ═══════════════════════════════════════════════════════════ */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
            }

            $venue = Venue::where('owner_id', $user->id)->find($id);

            if (!$venue && $user->role === 'admin') {
                $venue = Venue::find($id);
            }

            if (!$venue) {
                return response()->json([
                    'success' => false,
                    'message' => 'Venue not found or not yours.'
                ], 404);
            }

            DB::beginTransaction();

            // ✅ ምስል ሰርዝ
            if ($venue->image && Storage::disk('public')->exists($venue->image)) {
                Storage::disk('public')->delete($venue->image);
            }

            // ✅ ስኬጁል ሰርዝ (if relation exists)
            if (method_exists($venue, 'schedules')) {
                $venue->schedules()->delete();
            }

            $venue->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Venue deleted successfully.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Venue delete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete venue: ' . $e->getMessage()
            ], 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       NEW — GET schedule for a venue
       ═══════════════════════════════════════════════════════════ */
    public function getSchedule(Request $request, $id)
    {
        try {
            $user = $request->user();
            $venue = Venue::where('owner_id', $user->id)->find($id);

            if (!$venue && $user?->role === 'admin') {
                $venue = Venue::find($id);
            }

            if (!$venue) {
                return response()->json([
                    'success' => false,
                    'message' => 'Venue not found or not yours.'
                ], 404);
            }

            $schedules = method_exists($venue, 'schedules')
                ? $venue->schedules()->orderBy('day_of_week')->get()
                : [];

            return response()->json([
                'success' => true,
                'data' => $schedules
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching schedule: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch schedule'
            ], 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       NEW — SAVE schedule for a venue
       ═══════════════════════════════════════════════════════════ */
    public function saveSchedule(Request $request, $id)
    {
        try {
            $user = $request->user();
            $venue = Venue::where('owner_id', $user->id)->find($id);

            if (!$venue && $user?->role === 'admin') {
                $venue = Venue::find($id);
            }

            if (!$venue) {
                return response()->json([
                    'success' => false,
                    'message' => 'Venue not found or not yours.'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'schedule' => 'required|array|min:1',
                'schedule.*.day_of_week' => 'required|integer|between:0,6',
                'schedule.*.open_time' => 'required|string',
                'schedule.*.close_time' => 'required|string',
                'schedule.*.is_closed' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed: ' . $validator->errors()->first()
                ], 422);
            }

            DB::beginTransaction();

            if (!method_exists($venue, 'schedules')) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'VenueSchedule relation not defined on Venue model.'
                ], 500);
            }

            // ✅ አሮጌውን ሰርዝ፣ አዲሱን አስገባ
            $venue->schedules()->delete();

            foreach ($request->input('schedule', []) as $slot) {
                $venue->schedules()->create([
                    'day_of_week' => (int) $slot['day_of_week'],
                    'open_time' => $slot['open_time'],
                    'close_time' => $slot['close_time'],
                    'is_closed' => (bool) ($slot['is_closed'] ?? false),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Schedule saved successfully.',
                'data' => $venue->schedules()->orderBy('day_of_week')->get()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving schedule: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save schedule: ' . $e->getMessage()
            ], 500);
        }
    }
}