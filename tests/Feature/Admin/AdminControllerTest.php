<?php

namespace Tests\Feature\Admin;

use App\Models\Block;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Room;
use App\Models\Row;
use App\Models\Seat;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    /** @test */
    public function admin_can_view_dashboard()
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('title', 'Dashboard')
            ->has('stats')
            ->has('breadcrumbs')
        );
    }

    /** @test */
    public function dashboard_shows_correct_statistics()
    {
        $this->actingAs($this->admin);

        // Create test data
        $room1 = Room::factory()->create();
        $room2 = Room::factory()->create();

        // Create events (2 total, 1 upcoming)
        $pastEvent = Event::factory()->create([
            'room_id' => $room1->id,
            'starts_at' => Carbon::now()->subDays(1),
        ]);
        $futureEvent = Event::factory()->create([
            'room_id' => $room2->id,
            'starts_at' => Carbon::now()->addDays(1),
        ]);

        // Create seating structure and bookings
        $block = Block::factory()->seating()->create(['room_id' => $room1->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);
        $seat1 = Seat::factory()->create(['row_id' => $row->id]);
        $seat2 = Seat::factory()->create(['row_id' => $row->id]);

        // Create bookings (3 total)
        Booking::factory()->create(['event_id' => $pastEvent->id, 'seat_id' => $seat1->id]);
        Booking::factory()->create(['event_id' => $pastEvent->id, 'seat_id' => $seat2->id]);
        Booking::factory()->create(['event_id' => $futureEvent->id, 'seat_id' => $seat1->id]);

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('stats.totalEvents', 2)
            ->where('stats.upcomingEvents', 1)
            ->where('stats.totalBookings', 3)
            ->where('stats.totalRooms', 2)
        );
    }

    /** @test */
    public function dashboard_shows_zero_statistics_when_no_data()
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('stats.totalEvents', 0)
            ->where('stats.upcomingEvents', 0)
            ->where('stats.totalBookings', 0)
            ->where('stats.totalRooms', 0)
        );
    }

    /** @test */
    public function dashboard_counts_only_future_events_as_upcoming()
    {
        $this->actingAs($this->admin);

        $room = Room::factory()->create();

        // Create events with various dates
        Event::factory()->create([
            'room_id' => $room->id,
            'starts_at' => Carbon::now()->subDays(2), // Past
        ]);
        Event::factory()->create([
            'room_id' => $room->id,
            'starts_at' => Carbon::now()->subHour(), // Past
        ]);
        Event::factory()->create([
            'room_id' => $room->id,
            'starts_at' => Carbon::now()->addHour(), // Future
        ]);
        Event::factory()->create([
            'room_id' => $room->id,
            'starts_at' => Carbon::now()->addDays(1), // Future
        ]);

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('stats.totalEvents', 4)
            ->where('stats.upcomingEvents', 2) // Only future events
        );
    }

    /** @test */
    public function dashboard_includes_events_without_start_date()
    {
        $this->actingAs($this->admin);

        $room = Room::factory()->create();

        // Create events with and without start dates
        Event::factory()->create([
            'room_id' => $room->id,
            'starts_at' => Carbon::now()->addDays(1),
        ]);
        Event::factory()->create([
            'room_id' => $room->id,
            'starts_at' => null, // No start date
        ]);

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('stats.totalEvents', 2)
            ->where('stats.upcomingEvents', 1) // Only the one with future date
        );
    }

    /** @test */
    public function dashboard_counts_bookings_across_all_events()
    {
        $this->actingAs($this->admin);

        $room1 = Room::factory()->create();
        $room2 = Room::factory()->create();

        $event1 = Event::factory()->create(['room_id' => $room1->id]);
        $event2 = Event::factory()->create(['room_id' => $room2->id]);

        // Create seating for both rooms
        $block1 = Block::factory()->seating()->create(['room_id' => $room1->id]);
        $row1 = Row::factory()->create(['block_id' => $block1->id]);
        $seat1 = Seat::factory()->create(['row_id' => $row1->id]);

        $block2 = Block::factory()->seating()->create(['room_id' => $room2->id]);
        $row2 = Row::factory()->create(['block_id' => $block2->id]);
        $seat2 = Seat::factory()->create(['row_id' => $row2->id]);
        $seat3 = Seat::factory()->create(['row_id' => $row2->id]);

        // Create bookings for both events (need unique seat combinations)
        Booking::factory()->create(['event_id' => $event1->id, 'seat_id' => $seat1->id]);
        Booking::factory()->create(['event_id' => $event2->id, 'seat_id' => $seat2->id]);
        Booking::factory()->create(['event_id' => $event2->id, 'seat_id' => $seat3->id]);

        // Create additional seats and bookings for proper counts
        $seat4 = Seat::factory()->create(['row_id' => $row1->id]);
        $seat5 = Seat::factory()->create(['row_id' => $row2->id]);
        Booking::factory()->create(['event_id' => $event1->id, 'seat_id' => $seat4->id]);
        Booking::factory()->create(['event_id' => $event2->id, 'seat_id' => $seat5->id]);

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('stats.totalBookings', 5) // 2 + 3
        );
    }

    /** @test */
    public function dashboard_breadcrumbs_are_empty()
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('breadcrumbs', [])
        );
    }

    /** @test */
    public function non_admin_cannot_access_dashboard()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user);

        $response = $this->get(route('admin.dashboard'));
        $response->assertForbidden();
    }

    /** @test */
    public function unauthenticated_user_cannot_access_dashboard()
    {
        $response = $this->get(route('admin.dashboard'));
        $response->assertRedirect('/auth/login');
    }

    /** @test */
    public function dashboard_handles_large_numbers_correctly()
    {
        $this->actingAs($this->admin);

        // Create many rooms
        Room::factory()->count(50)->create();

        // Create many events
        $room = Room::factory()->create();
        Event::factory()->count(100)->create([
            'room_id' => $room->id,
            'starts_at' => Carbon::now()->addDays(1), // All upcoming
        ]);

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('stats.totalEvents', 100)
            ->where('stats.upcomingEvents', 100)
            ->where('stats.totalRooms', 51) // 50 + 1
        );
    }
}
