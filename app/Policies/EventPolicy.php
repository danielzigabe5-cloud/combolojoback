<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Determine if user can view event
     */
    public function view(User $user, Event $event): bool
    {
        // Anyone can view active events
        return $event->is_active;
    }

    /**
     * Determine if user can create an event
     */
    public function create(User $user): bool
    {
        // Anyone except admins can create events, but admin can also create
        return true;
    }

    /**
     * Determine if user can update an event
     */
    public function update(User $user, Event $event): bool
    {
        // Admin can update all, organizer can update their own
        return $user->isAdmin() || $event->isOrganizedBy($user);
    }

    /**
     * Determine if user can delete an event
     */
    public function delete(User $user, Event $event): bool
    {
        // Admin can delete all, organizer can delete their own
        return $user->isAdmin() || $event->isOrganizedBy($user);
    }

    /**
     * Determine if user can restore an event
     */
    public function restore(User $user, Event $event): bool
    {
        return $user->isAdmin() || $event->isOrganizedBy($user);
    }

    /**
     * Determine if user can permanently delete an event
     */
    public function forceDelete(User $user, Event $event): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if user can join an event
     */
    public function join(User $user, Event $event): bool
    {
        // User cannot join their own event
        if ($event->isOrganizedBy($user)) {
            return false;
        }

        // User cannot join if already joined
        if ($event->hasUserJoined($user)) {
            return false;
        }

        // Event must be active and not full
        return $event->is_active && !$event->isFull();
    }

    /**
     * Determine if user can leave an event
     */
    public function leave(User $user, Event $event): bool
    {
        // Can leave if joined and not the organizer
        return !$event->isOrganizedBy($user) && $event->hasUserJoined($user);
    }
}
