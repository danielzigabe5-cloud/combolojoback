<?php

namespace App\Http\Controllers\Api\Resources;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * Get all events (public)
     * GET /api/events
     */
    public function index(Request $request)
    {
        $events = Event::where('is_active', true)->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $events
        ]);
    }

    /**
     * Get event by ID (public)
     * GET /api/events/:id
     */
    public function show($id)
    {
        $event = Event::findOrFail($id);
        
        $this->authorize('view', $event);

        return response()->json([
            'success' => true,
            'data' => $event->load('organizer', 'participants')
        ]);
    }

    /**
     * Create an event (authenticated users)
     * POST /api/events
     */
    public function store(StoreEventRequest $request)
    {
        $this->authorize('create', Event::class);

        $event = Event::create([
            ...$request->validated(),
            'organizer_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Event created successfully',
            'data' => $event
        ], 201);
    }

    /**
     * Update an event
     * PUT /api/events/:id
     */
    public function update(UpdateEventRequest $request, $id)
    {
        $event = Event::findOrFail($id);
        
        $this->authorize('update', $event);

        $event->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Event updated successfully',
            'data' => $event
        ]);
    }

    /**
     * Delete an event
     * DELETE /api/events/:id
     */
    public function destroy($id)
    {
        $event = Event::findOrFail($id);
        
        $this->authorize('delete', $event);

        $event->delete();

        return response()->json([
            'success' => true,
            'message' => 'Event deleted successfully'
        ]);
    }

    /**
     * Join an event
     * POST /api/events/:id/join
     */
    public function join(Request $request, $id)
    {
        $user = $request->user();
        $event = Event::findOrFail($id);

        $this->authorize('join', $event);

        $event->participants()->attach($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Successfully joined the event'
        ]);
    }

    /**
     * Leave an event
     * POST /api/events/:id/leave
     */
    public function leave(Request $request, $id)
    {
        $user = $request->user();
        $event = Event::findOrFail($id);

        $this->authorize('leave', $event);

        $event->participants()->detach($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Successfully left the event'
        ]);
    }

    /**
     * Get user's events
     * GET /api/my-events
     */
    public function myEvents(Request $request)
    {
        $user = $request->user();
        $events = Event::whereHas('participants', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $events
        ]);
    }
}
