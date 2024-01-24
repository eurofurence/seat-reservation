<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Row extends Model
{
    protected $guarded = [];

    public function seats(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Seat::class);
    }

    public function block(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Block::class);
    }
}
