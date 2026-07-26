<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Block extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $fillable = [
        'room_id',
        'name',
        'type',
        'position_x',
        'position_y',
        'rotation',
        'colspan',
        'rowspan',
        'order',
    ];

    protected $casts = [
        'rotation' => 'integer',
        'position_x' => 'integer',
        'position_y' => 'integer',
        'colspan' => 'integer',
        'rowspan' => 'integer',
        'order' => 'integer',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function rows()
    {
        return $this->hasMany(Row::class)->orderBy('order');
    }

    // Scopes
    public function scopeSeating($query)
    {
        return $query->where('type', 'seating');
    }

    public function scopeStage($query)
    {
        return $query->where('type', 'stage');
    }

    // Non-bookable annotation blocks: entrance rows/columns and text comments.
    public function scopeMarker($query)
    {
        return $query->whereIn('type', ['entrance', 'comment']);
    }
}
