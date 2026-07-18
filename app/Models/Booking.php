<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $fillable = [
        'user_id',
        'event_id',
        'seat_id',
        'type',
        'booking_code',
        'name',
        'comment',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Eager-load the seat with its row and block using only the fields needed
     * to display a booking's seat location ("Block-Row-Label").
     */
    public function scopeWithSeatDetails(Builder $query): Builder
    {
        return $query->with([
            'seat:id,row_id,label,number',
            'seat.row:id,block_id,name',
            'seat.row.block:id,name',
        ]);
    }

    /**
     * Generate a 3-character A-Z/0-9 code (for ticket pickup). All seats booked in one
     * reservation intentionally share a single code, so booking_code is unique per
     * reservation, not per row — a DB-level column unique index would wrongly reject the
     * second seat of a multi-seat booking. The exists() check keeps codes distinct across
     * reservations; collisions are vanishingly unlikely for this low-volume admin workflow.
     */
    public static function generateUniqueCode(): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        // 36^3 = 46,656 codes; even at 90% utilisation the expected attempts stay < 10.
        // Cap at 20 to fail loudly rather than hang if the code space is genuinely exhausted.
        for ($attempts = 0; $attempts < 20; $attempts++) {
            $code = $characters[random_int(0, 35)].$characters[random_int(0, 35)].$characters[random_int(0, 35)];
            if (! static::where('booking_code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Could not allocate a unique 3-character booking code - the code space appears to be exhausted.');
    }
}
