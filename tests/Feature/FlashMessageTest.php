<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Room;
use App\Models\Row;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashMessageTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function flash_message_is_shared_once_then_cleared()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $room = Room::factory()->create();
        $block = Block::factory()->create(['room_id' => $room->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);
        $seat = Seat::factory()->create(['row_id' => $row->id]);
        $event = Event::factory()->create(['room_id' => $room->id]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'seat_id' => $seat->id,
        ]);

        // The action that sets the flash message.
        $this->actingAs($user)
            ->delete(route('bookings.destroy', [$event, $booking]))
            ->assertSessionHas('success', 'Booking cancelled!');

        // First page load after the redirect: flash is present, tagged with an id.
        $firstProps = $this->actingAs($user)
            ->get(route('bookings.index'))
            ->getOriginalContent()->getData()['page']['props'];

        $this->assertSame('Booking cancelled!', $firstProps['flash']['success']);
        $this->assertNotEmpty($firstProps['flash']['id']);

        // A later, unrelated page load must not repeat the same flash message.
        $secondProps = $this->actingAs($user)
            ->get(route('bookings.index'))
            ->getOriginalContent()->getData()['page']['props'];

        $this->assertNull($secondProps['flash']);
    }
}
