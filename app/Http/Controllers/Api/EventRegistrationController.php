<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventRegistration;
use Illuminate\Http\Request;

class EventRegistrationController extends Controller
{
    // POST /api/event-registrations
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'team_name' => 'nullable|string|max:255',
        ]);

        $registration = EventRegistration::create($validated);

        return response()->json([
            'message' => 'Registration successful!',
            'data' => $registration
        ], 201);
    }
}