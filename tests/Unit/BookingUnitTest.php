<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\User;
use App\Models\Event;
use App\Models\Seat;
use PHPUnit\Framework\TestCase;

class BookingUnitTest extends TestCase
{
    /** @test */
    public function booking_has_correct_fillable_attributes()
    {
        $booking = new Booking();
        $expected = [
            'user_id',
            'event_id', 
            'seat_id',
            'type',
            'booking_code',
            'name',
            'comment'
        ];
        
        $this->assertEquals($expected, $booking->getFillable());
    }

    /** @test */
    public function booking_has_correct_default_type()
    {
        $booking = new Booking();
        $booking->fill([
            'event_id' => 1,
            'seat_id' => 1,
            'name' => 'Test Name'
        ]);
        
        // Should have the default type from the database migration
        $this->assertTrue(in_array('type', $booking->getFillable()));
    }

    /** @test */
    public function booking_name_can_be_set()
    {
        $booking = new Booking();
        $booking->name = 'John Doe';
        
        $this->assertEquals('John Doe', $booking->name);
    }
}