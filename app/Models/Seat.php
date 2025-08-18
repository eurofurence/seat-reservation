<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seat extends Model
{
    protected $guarded = [];

    protected $fillable = [
        'row_id',
        'number',
        'label',
        'sort'
    ];

    protected $casts = [
        'number' => 'integer',
        'sort' => 'integer'
    ];

    public function row()
    {
        return $this->belongsTo(Row::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function isBookedForEvent($eventId)
    {
        return $this->bookings()->where('event_id', $eventId)->exists();
    }

    public function getFullLabel()
    {
        $this->loadMissing('row.block');
        return "{$this->row->block->name}-{$this->row->name}-{$this->label}";
    }
}