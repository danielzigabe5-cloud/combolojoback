<?php

namespace App\Policies;

use App\Models\Game;
use App\Models\User;

class GamePolicy
{
    /**
     * Determine if user can view game
     */
    public function view(User $user, Game $game): bool
    {
        // Anyone can view active games
        return $game->is_active;
    }

    /**
     * Determine if user can create a game
     */
    public function create(User $user): bool
    {
        // Anyone can create a game
        return true;
    }

    /**
     * Determine if user can update a game
     */
    public function update(User $user, Game $game): bool
    {
        // Admin can update all, organizer can update their own
        return $user->isAdmin() || $game->isOrganizedBy($user);
    }

    /**
     * Determine if user can delete a game
     */
    public function delete(User $user, Game $game): bool
    {
        // Admin can delete all, organizer can delete their own
        return $user->isAdmin() || $game->isOrganizedBy($user);
    }

    /**
     * Determine if user can restore a game
     */
    public function restore(User $user, Game $game): bool
    {
        return $user->isAdmin() || $game->isOrganizedBy($user);
    }

    /**
     * Determine if user can permanently delete a game
     */
    public function forceDelete(User $user, Game $game): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if user can join a game
     */
    public function join(User $user, Game $game): bool
    {
        // User cannot join their own game
        if ($game->isOrganizedBy($user)) {
            return false;
        }

        // User cannot join if already joined
        if ($game->hasUserJoined($user)) {
            return false;
        }

        // Game must be active and not full
        return $game->is_active && !$game->isFull();
    }

    /**
     * Determine if user can leave a game
     */
    public function leave(User $user, Game $game): bool
    {
        // Can leave if joined and not the organizer
        return !$game->isOrganizedBy($user) && $game->hasUserJoined($user);
    }
}
