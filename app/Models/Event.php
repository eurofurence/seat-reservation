<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $guarded = [];

    // appends
    protected $appends = ['tickets_left'];

    protected $casts = [
        'starts_at' => 'datetime',
        'reservation_ends_at' => 'datetime',
    ];

    public function room(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function bookings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Booking::class);
    }

    // custom tickets left attribute
    public function getTicketsLeftAttribute(): int
    {
        $bookedTickets = $this->bookings()->sum('number_of_tickets');
        return max(0, $this->tickets - $bookedTickets);
    }
}
