<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'datetime',
        'reservation_ends_at' => 'datetime',
        'booking_starts_at' => 'datetime',
        'tickets' => 'integer',
        'max_tickets' => 'integer',
        'notifications_sent' => 'array',
    ];

    public function isBookingOpen(): bool
    {
        return is_null($this->booking_starts_at) || $this->booking_starts_at->isPast();
    }

    // "Locked" state (see EventIndex.vue::getEventStatus) - reservation deadline has passed.
    public function isReservationClosed(): bool
    {
        return $this->reservation_ends_at !== null && $this->reservation_ends_at->isPast();
    }

    // "Completed" state (see EventIndex.vue::getEventStatus) - the event itself has already started.
    public function hasEnded(): bool
    {
        return $this->starts_at !== null && $this->starts_at->isPast();
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    // Counts how many seats are still bookable.
    public function calculateTicketsLeft(): int
    {
        $maxTickets = $this->max_tickets ?? $this->tickets ?? 0;
        if ($maxTickets === 0) {
            // If no ticket limit is set, use seat availability
            $maxTickets = $this->room?->totalSeatsCount() ?? 0;
        }

        $bookedTickets = $this->bookings()->count(); // Each booking = 1 ticket

        return max(0, $maxTickets - $bookedTickets);
    }
}
