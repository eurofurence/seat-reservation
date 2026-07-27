<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BookingPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Booking $booking): bool
    {
        // Admins can view any booking
        if ($user->is_admin) {
            return true;
        }

        // Regular users can only view their own bookings
        return $user->id === $booking->user_id;
    }

    public function update(User $user, Booking $booking): bool
    {
        // Admins can update any booking
        if ($user->is_admin) {
            return true;
        }

        // Regular users can only update their own bookings before deadline and if not picked up
        return $user->id === $booking->user_id
            && ! $booking->event->isReservationClosed()
            && is_null($booking->picked_up_at);
    }

    public function delete(User $user, Booking $booking): bool
    {
        // Admins can delete any booking
        if ($user->is_admin) {
            return true;
        }

        // Regular users can only delete their own bookings before deadline and if not picked up
        return $user->id === $booking->user_id
            && ! $booking->event->isReservationClosed()
            && is_null($booking->picked_up_at);
    }
}
