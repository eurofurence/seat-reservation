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
        return $user->id === $booking->user_id;
    }

    public function create(User $user, Event|null $event = null): bool
    {
        if ($user->is_admin) {
            return true;
        }
        // only if the total number of bookings does not exceed the maximum number of tickets
        if ($event === null) {
            return false;
        }

        if($event->bookings()->count() >= $event->max_tickets) {
            return false;
        }

        return $event->reservation_ends_at->isFuture();
    }

    public function update(User $user, Booking $booking): bool
    {
        return $user->id === $booking->user_id && $booking->event->reservation_ends_at->isFuture();
    }

    public function delete(User $user, Booking $booking): bool
    {
        return $user->id === $booking->user_id && $booking->event->reservation_ends_at->isFuture();
    }
}
