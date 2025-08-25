<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $guarded = [];

    // appends - disabled to prevent heavy loading
    // protected $appends = ['tickets_left'];

    protected $casts = [
        'starts_at' => 'datetime',
        'reservation_ends_at' => 'datetime',
        'tickets' => 'integer',
        'max_tickets' => 'integer',
        'notifications_sent' => 'array'
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    // custom tickets left attribute - count how many seats are booked
    public function getTicketsLeftAttribute(): int
    {
        $maxTickets = $this->max_tickets ?? $this->tickets ?? 0;
        if ($maxTickets === 0) {
            // If no ticket limit is set, use seat availability
            $totalSeats = $this->room->blocks()->with('rows.seats')->get()
                ->flatMap(fn($block) => $block->rows->flatMap(fn($row) => $row->seats))->count();
            $maxTickets = $totalSeats;
        }
        
        $bookedTickets = $this->bookings()->count(); // Each booking = 1 ticket
        return max(0, $maxTickets - $bookedTickets);
    }
}
