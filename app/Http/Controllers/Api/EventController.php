<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    // GET /api/events
    public function index()
    {
        $events = Event::latest()->get();

        return response()->json([
            'data' => $events
        ], 200);
    }

    // POST /api/events (ለAdmin ክስተቶችን ለመጨመር የሚያገለግል)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string',
            'status' => 'required|in:Upcoming,Ongoing,Completed',
            'type' => 'required|string',
            'date' => 'required|string',
            'location' => 'required|string',
            'price' => 'required|string',
            'image_url' => 'nullable|url',
        ]);

        $event = Event::create($validated);

        return response()->json([
            'message' => 'Event created successfully',
            'data' => $event
        ], 201);
    }
}