<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Block extends Model
{
    protected $fillable = [
        'name',
        'room_id',
        'position_x',
        'position_y',
        'rotation',
        'z_index',
        'grid_column',
        'grid_row',
        'grid_column_span',
        'grid_row_span'
    ];

    protected $casts = [
        'position_x' => 'integer',
        'position_y' => 'integer',
        'rotation' => 'integer',
        'z_index' => 'integer',
        'grid_column' => 'integer',
        'grid_row' => 'integer',
        'grid_column_span' => 'integer',
        'grid_row_span' => 'integer',
    ];

    public function rows()
    {
        return $this->hasMany(Row::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
