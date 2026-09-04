<?php

namespace App\Policies;

use App\Models\Venue;
use App\Models\User;

class VenuePolicy
{
    public function view(User $user, Venue $venue): bool
    {
        return $venue->is_active;
    }

    public function create(User $user): bool
    {
        return $user->isOwner() || $user->isAdmin();
    }

    public function update(User $user, Venue $venue): bool
    {
        return $user->isAdmin() || $venue->isOwnedBy($user);
    }

    public function delete(User $user, Venue $venue): bool
    {
        return $user->isAdmin() || $venue->isOwnedBy($user);
    }

    public function restore(User $user, Venue $venue): bool
    {
        return $user->isAdmin() || $venue->isOwnedBy($user);
    }

    public function forceDelete(User $user, Venue $venue): bool
    {
        return $user->isAdmin();
    }
}