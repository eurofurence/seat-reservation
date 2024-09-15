<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Block extends Model
{
    protected $guarded = [];

    public function rows()
    {
        return $this->hasMany(Row::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
