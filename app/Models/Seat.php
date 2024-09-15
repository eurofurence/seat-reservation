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

    public function getFullName()
    {
        // load
        $this->loadMissing('row.block');
        // Block - Row - Seat
        return "{$this->row->block->name} - {$this->row->name} - {$this->name}";
    }
}
