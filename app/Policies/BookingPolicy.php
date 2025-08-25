<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BookingPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, Booking $booking): bool
    {
        // Admins can view any booking
        if ($user->is_admin) {
            return true;
        }
        
        // Regular users can only view their own bookings
        return $user->id === $booking->user_id;
    }

    public function create(User $user, ?Event $event = null): bool
    {
        if ($user->is_admin) {
            return true;
        }
        // only if the total number of bookings does not exceed the maximum number of tickets
        if ($event === null) {
            return false;
        }

        if ($event->bookings()->count() >= $event->max_tickets) {
            return false;
        }

        return $event->reservation_ends_at->isFuture();
    }

    public function update(User $user, Booking $booking): bool
    {
        // Admins can update any booking
        if ($user->is_admin) {
            return true;
        }
        
        // Regular users can only update their own bookings before deadline and if not picked up
        return $user->id === $booking->user_id
            && $booking->event->reservation_ends_at->isFuture()
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
            && $booking->event->reservation_ends_at->isFuture()
            && is_null($booking->picked_up_at);
    }
}
