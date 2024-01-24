<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seat extends Model
{
    protected $guarded = [];

    public function row(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Row::class);
    }

    public function bookings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
