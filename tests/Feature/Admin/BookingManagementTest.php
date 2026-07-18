<?php

namespace Tests\Feature\Admin;

use App\Models\Block;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Room;
use App\Models\Row;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingManagementTest extends TestCase
{
    use RefreshDatabase;

    private $admin;

    private $event;

    private $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();

        $room = Room::factory()->create();
        $block = Block::factory()->create(['room_id' => $room->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);
        $seat = Seat::factory()->create(['row_id' => $row->id]);

        $this->event = Event::factory()->create(['room_id' => $room->id]);
        $this->booking = Booking::factory()->create([
            'event_id' => $this->event->id,
            'seat_id' => $seat->id,
            'name' => 'Original Name',
            'picked_up_at' => null,
        ]);
    }

    /** @test */
    public function admin_can_toggle_pickup_status_on_and_off()
    {
        $response = $this->actingAs($this->admin)
            ->from(route('admin.events.show', $this->event))
            ->post(route('admin.events.toggle-pickup', $this->event), [
                'booking_id' => $this->booking->id,
                'picked_up' => true,
            ]);

        $response->assertRedirect(route('admin.events.show', $this->event))
            ->assertSessionHas('success', 'Marked as picked up');
        $this->assertNotNull($this->booking->fresh()->picked_up_at);

        $response = $this->actingAs($this->admin)
            ->from(route('admin.events.show', $this->event))
            ->post(route('admin.events.toggle-pickup', $this->event), [
                'booking_id' => $this->booking->id,
                'picked_up' => false,
            ]);

        $response->assertRedirect(route('admin.events.show', $this->event))
            ->assertSessionHas('success', 'Marked as not picked up');
        $this->assertNull($this->booking->fresh()->picked_up_at);
    }

    /** @test */
    public function toggle_pickup_validates_input_and_rejects_bookings_of_other_events()
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.events.toggle-pickup', $this->event), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['booking_id', 'picked_up']);

        $otherEvent = Event::factory()->create(['room_id' => $this->event->room_id]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.toggle-pickup', $otherEvent), [
                'booking_id' => $this->booking->id,
                'picked_up' => true,
            ])
            ->assertNotFound();

        $this->assertNull($this->booking->fresh()->picked_up_at);
    }

    /** @test */
    public function admin_can_update_a_booking()
    {
        $response = $this->actingAs($this->admin)
            ->put(route('admin.events.update-booking', [$this->event, $this->booking]), [
                'name' => 'Updated Name',
                'comment' => 'A comment',
            ]);

        $response->assertRedirect()->assertSessionHas('success', 'Booking updated successfully');
        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'name' => 'Updated Name',
            'comment' => 'A comment',
        ]);
    }

    /** @test */
    public function update_booking_validates_input_and_rejects_bookings_of_other_events()
    {
        $this->actingAs($this->admin)
            ->put(route('admin.events.update-booking', [$this->event, $this->booking]), ['name' => ''])
            ->assertSessionHasErrors(['name']);

        $otherEvent = Event::factory()->create(['room_id' => $this->event->room_id]);

        $this->actingAs($this->admin)
            ->put(route('admin.events.update-booking', [$otherEvent, $this->booking]), ['name' => 'New Name'])
            ->assertNotFound();

        $this->assertSame('Original Name', $this->booking->fresh()->name);
    }

    /** @test */
    public function admin_can_delete_a_booking()
    {
        $response = $this->actingAs($this->admin)
            ->delete(route('admin.events.delete-booking', [$this->event, $this->booking]));

        $response->assertRedirect()->assertSessionHas('success', 'Booking deleted successfully');
        $this->assertDatabaseMissing('bookings', ['id' => $this->booking->id]);
    }

    /** @test */
    public function delete_booking_rejects_bookings_of_other_events()
    {
        $otherEvent = Event::factory()->create(['room_id' => $this->event->room_id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.events.delete-booking', [$otherEvent, $this->booking]))
            ->assertNotFound();

        $this->assertDatabaseHas('bookings', ['id' => $this->booking->id]);
    }

    /** @test */
    public function guests_and_non_admins_cannot_manage_bookings()
    {
        $this->delete(route('admin.events.delete-booking', [$this->event, $this->booking]))
            ->assertRedirect(route('auth.login'));

        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)
            ->delete(route('admin.events.delete-booking', [$this->event, $this->booking]))
            ->assertForbidden();

        $this->assertDatabaseHas('bookings', ['id' => $this->booking->id]);
    }
}
