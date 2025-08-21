<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $guarded = [];

    protected $casts = [
        'stage_x' => 'integer',
        'stage_y' => 'integer'
    ];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class)->seating()->orderBy('order');
    }

    public function stageBlocks(): HasMany
    {
        return $this->hasMany(Block::class)->stage()->orderBy('order');
    }

    public function allBlocks(): HasMany
    {
        return $this->hasMany(Block::class)->orderBy('order');
    }
}
