<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Row extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $guarded = [];

    protected $fillable = [
        'block_id',
        'name',
        'order',
        'seats_count',
        'custom_seat_count'
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