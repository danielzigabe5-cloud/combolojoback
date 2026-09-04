<?php

namespace App\Http\Controllers\Api\Resources;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Http\Requests\StoreVenueRequest;
use App\Http\Requests\UpdateVenueRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VenueController extends Controller
{
    /**
     * Get all venues (public)
     * GET /api/venues
     */
    public function index(Request $request)
    {
        $query = Venue::where('is_active', true);
        
        // Filter by city
        if ($request->has('city') && $request->city !== 'All' && $request->city !== '') {
            $query->where('city', $request->city);
        }
        
        // Filter by sub city
        if ($request->has('sub_city') && $request->sub_city !== 'All' && $request->sub_city !== '') {
            $query->where('sub_city', $request->sub_city);
        }
        
        // Filter by sport
        if ($request->has('sport') && $request->sport !== 'All' && $request->sport !== '') {
            $query->whereJsonContains('sport_types', $request->sport);
        }
        
        // Filter by price
        if ($request->has('max_price') && $request->max_price > 0) {
            $query->where('price_per_hour', '<=', $request->max_price);
        }
        
        // Search
        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('location', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }
        
        // Sort
        $sortBy = $request->sort_by ?? 'id';
        $sortOrder = $request->sort_order ?? 'desc';
        
        if ($sortBy === 'price_asc') {
            $query->orderBy('price_per_hour', 'asc');
        } elseif ($sortBy === 'price_desc') {
            $query->orderBy('price_per_hour', 'desc');
        } elseif ($sortBy === 'rating') {
            $query->orderBy('rating', 'desc');
        } elseif ($sortBy === 'distance') {
            $query->orderBy('distance', 'asc');
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }
        
        $venues = $query->paginate($request->per_page ?? 15);
        
        return response()->json([
            'success' => true,
            'data' => $venues
        ]);
    }

    /**
     * Get venue by ID (public)
     * GET /api/venues/:id
     */
    public function show($id)
    {
        $venue = Venue::with(['owner', 'bookings' => function ($query) {
            $query->where('status', 'confirmed')
                  ->where('end_time', '>=', now());
        }])->findOrFail($id);
        
        if (!$venue->is_active && !auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Venue not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $venue
        ]);
    }

    /**
     * Create a new venue
     * POST /api/venues
     */public function store(StoreVenueRequest $request)
    {
        $user = $request->user();
        
        // Parse sport_types and facilities
        $sportTypes = $request->has('sport_types') 
            ? json_decode($request->input('sport_types'), true) 
            : [];
        
        $facilities = $request->has('facilities') 
            ? json_decode($request->input('facilities'), true) 
            : [];
        
        // Handle image upload
        $imageUrl = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('venues', 'public');
            $imageUrl = asset('storage/' . $path);
        }
        
        // Create venue - ALWAYS is_active = false (needs admin approval)
        $venue = Venue::create([
            'name' => $request->name,
            'description' => $request->description,
            'location' => $request->location,
            'city' => $request->city,
            'sub_city' => $request->sub_city,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'capacity' => $request->capacity,
            'price_per_hour' => $request->price_per_hour,
            'image_url' => $imageUrl,
            'owner_id' => $user->id,
            'is_active' => false, // ALWAYS false - needs admin approval
            'sport_types' => $sportTypes,
            'facilities' => $facilities,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Venue registered successfully! Please wait for admin approval.',
            'data' => $venue
        ], 201);
    }

    /**
     * Update a venue
     * PUT /api/venues/:id
     */
    public function update(UpdateVenueRequest $request, $id)
    {
        $venue = Venue::findOrFail($id);
        $user = $request->user();
        
        if (!$user->isAdmin() && $venue->owner_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this venue'
            ], 403);
        }
        
        // Handle image upload
        if ($request->hasFile('image')) {
            if ($venue->image_url) {
                $oldPath = str_replace(asset('storage/'), '', $venue->image_url);
                Storage::disk('public')->delete($oldPath);
            }
            
            $path = $request->file('image')->store('venues', 'public');
            $request->merge(['image_url' => asset('storage/' . $path)]);
        }
        
        // Parse sport_types and facilities
        if ($request->has('sport_types')) {
            $request->merge(['sport_types' => json_decode($request->input('sport_types'), true)]);
        }
        
        if ($request->has('facilities')) {
            $request->merge(['facilities' => json_decode($request->input('facilities'), true)]);
        }
        
        $venue->update($request->validated());
        
        return response()->json([
            'success' => true,
            'message' => 'Venue updated successfully',
            'data' => $venue
        ]);
    }

    /**
     * Delete a venue
     * DELETE /api/venues/:id
     */
    public function destroy(Request $request, $id)
    {
        $venue = Venue::findOrFail($id);
        $user = $request->user();
        
        if (!$user->isAdmin() && $venue->owner_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this venue'
            ], 403);
        }
        
        if ($venue->image_url) {
            $path = str_replace(asset('storage/'), '', $venue->image_url);
            Storage::disk('public')->delete($path);
        }
        
        $venue->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Venue deleted successfully'
        ]);
    }

    /**
     * Get owner's venues
     * GET /api/my-venues
     */
    public function myVenues(Request $request)
    {
        $user = $request->user();
        
        $query = Venue::where('owner_id', $user->id);
        
        if ($request->has('status') && $request->status !== 'all') {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }
        
        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('location', 'LIKE', "%{$search}%");
            });
        }
        
        $sortBy = $request->sort_by ?? 'id';
        $sortOrder = $request->sort_order ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);
        
        $venues = $query->paginate($request->per_page ?? 15);
        
        $venues->getCollection()->each(function ($venue) {
            $venue->bookings_count = $venue->bookings()->count();
        });
        
        return response()->json([
            'success' => true,
            'data' => $venues
        ]);
    }
}