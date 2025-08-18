<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Block extends Model
{
    protected $guarded = [];

    protected $fillable = [
        'room_id',
        'name',
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
}