<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $guarded = [];

    // appends
    protected $appends = ['seats_left'];

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

    // custom seats left attribute
    public function getSeatsLeftAttribute(): int
    {
        return min(
            Seat::whereHas('row.block.room.events', function ($query) {
                $query->where('id', $this->id);
            })->whereDoesntHave('bookings', function ($query) {
                $query->where('event_id', $this->id);
            })->count(),
            $this->tickets - $this->bookings()->count()
        );
    }
}
