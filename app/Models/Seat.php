<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seat extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $fillable = [
        'row_id',
        'number',
        'label',
    ];

    protected $casts = [
        'number' => 'integer',
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

    // Accessor to return label when name is null
    public function getNameAttribute($value)
    {
        return $value ?: $this->label;
    }
}
