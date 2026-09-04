<?php

namespace App\Http\Controllers\Api\Resources;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Http\Requests\StoreGameRequest;
use App\Http\Requests\UpdateGameRequest;
use Illuminate\Http\Request;

class GameController extends Controller
{
    /**
     * Get all games (public)
     * GET /api/games
     */
    public function index(Request $request)
    {
        $games = Game::where('is_active', true)->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $games
        ]);
    }

    /**
     * Get game by ID (public)
     * GET /api/games/:id
     */
    public function show($id)
    {
        $game = Game::findOrFail($id);
        
        $this->authorize('view', $game);

        return response()->json([
            'success' => true,
            'data' => $game->load('organizer', 'players', 'venue')
        ]);
    }

    /**
     * Create a game (authenticated users)
     * POST /api/games
     */
    public function store(StoreGameRequest $request)
    {
        $this->authorize('create', Game::class);

        $game = Game::create([
            ...$request->validated(),
            'organizer_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Game created successfully',
            'data' => $game
        ], 201);
    }

    /**
     * Update a game
     * PUT /api/games/:id
     */
    public function update(UpdateGameRequest $request, $id)
    {
        $game = Game::findOrFail($id);
        
        $this->authorize('update', $game);

        $game->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Game updated successfully',
            'data' => $game
        ]);
    }

    /**
     * Delete a game
     * DELETE /api/games/:id
     */
    public function destroy($id)
    {
        $game = Game::findOrFail($id);
        
        $this->authorize('delete', $game);

        $game->delete();

        return response()->json([
            'success' => true,
            'message' => 'Game deleted successfully'
        ]);
    }

    /**
     * Join a game
     * POST /api/games/:id/join
     */
    public function join(Request $request, $id)
    {
        $user = $request->user();
        $game = Game::findOrFail($id);

        $this->authorize('join', $game);

        $game->players()->attach($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Successfully joined the game'
        ]);
    }

    /**
     * Leave a game
     * POST /api/games/:id/leave
     */
    public function leave(Request $request, $id)
    {
        $user = $request->user();
        $game = Game::findOrFail($id);

        $this->authorize('leave', $game);

        $game->players()->detach($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Successfully left the game'
        ]);
    }

    /**
     * Get user's games
     * GET /api/my-games
     */
    public function myGames(Request $request)
    {
        $user = $request->user();
        $games = Game::whereHas('players', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $games
        ]);
    }
}
