<?php

namespace Tests\Unit;

use App\Booking\SeatAssigner;
use PHPUnit\Framework\TestCase;

class SeatAssignerTest extends TestCase
{
    /** @test */
    public function contiguous_room_run_does_not_cross_block_boundaries()
    {
        // Two adjacent blocks with exactly 1 free seat each at the seam. A naive room-wide
        // scan across the flat concatenation [10, 20] would happily return a "contiguous"
        // pair of 2, but those seats aren't physically next to each other.
        $seatIdsByBlock = [
            [1, 2, 10],  // block A: seats 1, 2 booked; seat 10 free at end
            [20, 21, 22], // block B: seat 20 free at start; 21, 22 booked
        ];
        $unavailable = [1, 2, 21, 22];

        $assigner = new SeatAssigner;

        $this->assertNull(
            $assigner->findContiguousRoomRun($seatIdsByBlock, $unavailable, 2),
            'A run of 2 must not span the last seat of one block and the first of the next.'
        );

        // Sanity check: same input, quantity 1 finds the first block's free seat.
        $this->assertSame([10], $assigner->findContiguousRoomRun($seatIdsByBlock, $unavailable, 1));
    }

    /** @test */
    public function contiguous_room_run_returns_first_fitting_block()
    {
        $seatIdsByBlock = [
            [1, 2, 3],       // fully booked
            [10, 11, 12, 13], // 10, 11, 12 free; 13 booked
            [20, 21, 22],    // all free
        ];
        $unavailable = [1, 2, 3, 13];

        $assigner = new SeatAssigner;

        // First block that can fit 3 contiguous is block 2 (seats 10, 11, 12).
        $this->assertSame([10, 11, 12], $assigner->findContiguousRoomRun($seatIdsByBlock, $unavailable, 3));
    }
}
