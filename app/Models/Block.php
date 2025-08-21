<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Block extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    protected $fillable = [
        'room_id',
        'name',
        'type',
        'position_x',
        'position_y',
        'rotation',
        'order'
    ];

    protected $casts = [
        'rotation' => 'integer',
        'position_x' => 'integer',
        'position_y' => 'integer',
        'order' => 'integer'
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
}