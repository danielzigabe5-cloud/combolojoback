<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /**
     * Determine if user can view booking
     */
    public function view(User $user, Booking $booking): bool
    {
        // User can view their own bookings, venue owner can view bookings for their venues, admin can view all
        return $user->isAdmin() || 
               $booking->isOwnedBy($user) || 
               $booking->venue->isOwnedBy($user);
    }

    /**
     * Determine if user can create a booking
     */
    public function create(User $user): bool
    {
        // Anyone can create a booking
        return true;
    }

    /**
     * Determine if user can update a booking
     */
    public function update(User $user, Booking $booking): bool
    {
        // Only the user who made the booking can update, and only if pending
        return $booking->isOwnedBy($user) && $booking->status === 'pending';
    }

    /**
     * Determine if user can cancel a booking
     */
    public function cancel(User $user, Booking $booking): bool
    {
        // User who made booking can cancel if not completed
        if ($booking->isOwnedBy($user) && $booking->status !== 'completed') {
            return true;
        }

        // Venue owner can cancel their bookings
        if ($booking->venue->isOwnedBy($user) && $booking->status !== 'completed') {
            return true;
        }

        // Admin can cancel any booking
        return $user->isAdmin();
    }

    /**
     * Determine if user can delete a booking
     */
    public function delete(User $user, Booking $booking): bool
    {
        // Admin can delete any booking
        return $user->isAdmin();
    }

    /**
     * Determine if user can confirm a booking
     */
    public function confirm(User $user, Booking $booking): bool
    {
        // Venue owner can confirm bookings for their venues
        // Admin can confirm any booking
        return ($booking->venue->isOwnedBy($user) || $user->isAdmin()) && $booking->status === 'pending';
    }
}
