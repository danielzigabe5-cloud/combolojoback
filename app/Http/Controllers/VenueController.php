<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class VenueController extends Controller
{
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
                'sport_types' => json_encode($sportTypes),
                'facilities' => json_encode($facilities),
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

    public function myVenues(Request $request)
    {
        try {
            $user = $request->user();
            $venues = Venue::where('owner_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

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
}