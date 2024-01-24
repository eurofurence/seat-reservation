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
        return false;
    }

    public function view(User $user, Booking $booking): bool
    {
        return $user->id === $booking->user_id;
    }

    public function create(User $user, Event $event): bool
    {
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
