<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Row extends Model
{
    protected $guarded = [];

    protected $fillable = [
        'block_id',
        'name',
        'order'
    ];

    protected $casts = [
        'order' => 'integer'
    ];

    public function block()
    {
        return $this->belongsTo(Block::class);
    }

    public function seats()
    {
        return $this->hasMany(Seat::class)->orderBy('number');
    }
}