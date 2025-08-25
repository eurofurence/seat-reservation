<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Room;
use App\Models\Block;
use App\Models\Row;
use App\Models\Seat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class RoomAdminControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    /** @test */
    public function admin_can_view_rooms_index()
    {
        $this->actingAs($this->admin);
        
        $room1 = Room::factory()->create(['name' => 'Room 1']);
        $room2 = Room::factory()->create(['name' => 'Room 2']);

        // Add some blocks and seats for testing counts
        $block = Block::factory()->seating()->create(['room_id' => $room1->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);
        $seat = Seat::factory()->create(['row_id' => $row->id]);

        $response = $this->get(route('admin.rooms.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/RoomIndex')
            ->has('rooms', 2)
            ->where('title', 'Rooms')
            ->where('rooms.0.name', 'Room 1')
            ->where('rooms.0.blocks_count', 1)
            ->where('rooms.0.total_seats', 1)
        );
    }

    /** @test */
    public function admin_can_create_room()
    {
        $this->actingAs($this->admin);

        $roomData = [
            'name' => 'New Conference Room',
        ];

        $response = $this->post(route('admin.rooms.store'), $roomData);

        $response->assertRedirect(route('admin.rooms.index'));
        
        $this->assertDatabaseHas('rooms', [
            'name' => 'New Conference Room',
            'stage_x' => 0,
            'stage_y' => 0,
        ]);
    }

    /** @test */
    public function room_creation_validates_required_fields()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.rooms.store'), []);

        $response->assertSessionHasErrors(['name']);
    }

    /** @test */
    public function room_name_has_max_length_validation()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.rooms.store'), [
            'name' => str_repeat('a', 256), // Exceeds max length of 255
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    /** @test */
    public function admin_can_view_room_edit_page()
    {
        $this->actingAs($this->admin);
        $room = Room::factory()->create(['name' => 'Test Room']);

        $response = $this->get(route('admin.rooms.edit', $room->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/RoomEdit')
            ->where('room.id', $room->id)
            ->where('room.name', 'Test Room')
            ->where('title', 'Edit Room - Test Room')
            ->has('breadcrumbs', 2)
        );
    }

    /** @test */
    public function admin_can_update_room()
    {
        $this->actingAs($this->admin);
        $room = Room::factory()->create(['name' => 'Old Room Name']);

        $updateData = [
            'name' => 'Updated Room Name',
        ];

        $response = $this->put(route('admin.rooms.update', $room->id), $updateData);

        $response->assertRedirect(route('admin.rooms.index'));
        
        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'name' => 'Updated Room Name',
        ]);
    }

    /** @test */
    public function room_update_validates_required_fields()
    {
        $this->actingAs($this->admin);
        $room = Room::factory()->create();

        $response = $this->put(route('admin.rooms.update', $room->id), [
            'name' => '',
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    /** @test */
    public function room_update_validates_max_length()
    {
        $this->actingAs($this->admin);
        $room = Room::factory()->create();

        $response = $this->put(route('admin.rooms.update', $room->id), [
            'name' => str_repeat('a', 256), // Exceeds max length
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    /** @test */
    public function admin_can_delete_room()
    {
        $this->actingAs($this->admin);
        $room = Room::factory()->create(['name' => 'Room to Delete']);

        $response = $this->delete(route('admin.rooms.destroy', $room->id));

        $response->assertRedirect(route('admin.rooms.index'));
        $response->assertSessionHas('success', "Room 'Room to Delete' has been deleted successfully.");
        
        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
    }

    /** @test */
    public function delete_room_cascades_to_related_models()
    {
        $this->actingAs($this->admin);
        $room = Room::factory()->create();
        
        // Create related models
        $block = Block::factory()->seating()->create(['room_id' => $room->id]);
        $row = Row::factory()->create(['block_id' => $block->id]);
        $seat = Seat::factory()->create(['row_id' => $row->id]);

        $response = $this->delete(route('admin.rooms.destroy', $room->id));

        $response->assertRedirect(route('admin.rooms.index'));
        
        // Verify cascade deletion
        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
        $this->assertDatabaseMissing('blocks', ['id' => $block->id]);
        $this->assertDatabaseMissing('rows', ['id' => $row->id]);
        $this->assertDatabaseMissing('seats', ['id' => $seat->id]);
    }

    /** @test */
    public function rooms_index_shows_correct_seat_counts()
    {
        $this->actingAs($this->admin);
        
        $room1 = Room::factory()->create(['name' => 'Room 1']);
        $room2 = Room::factory()->create(['name' => 'Room 2']);

        // Room 1: 2 blocks, 3 rows total, 5 seats total
        $block1 = Block::factory()->seating()->create(['room_id' => $room1->id]);
        $row1 = Row::factory()->create(['block_id' => $block1->id]);
        Seat::factory()->count(3)->create(['row_id' => $row1->id]);

        $block2 = Block::factory()->seating()->create(['room_id' => $room1->id]);
        $row2 = Row::factory()->create(['block_id' => $block2->id]);
        Seat::factory()->count(2)->create(['row_id' => $row2->id]);

        // Room 2: 1 block, 1 row, 1 seat
        $block3 = Block::factory()->seating()->create(['room_id' => $room2->id]);
        $row3 = Row::factory()->create(['block_id' => $block3->id]);
        Seat::factory()->create(['row_id' => $row3->id]);

        $response = $this->get(route('admin.rooms.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('rooms.0.blocks_count', 2)
            ->where('rooms.0.total_seats', 5)
            ->where('rooms.1.blocks_count', 1)
            ->where('rooms.1.total_seats', 1)
        );
    }

    /** @test */
    public function room_not_found_returns_404()
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.rooms.edit', 999));
        $response->assertNotFound();

        $response = $this->put(route('admin.rooms.update', 999), ['name' => 'Test']);
        $response->assertNotFound();

        $response = $this->delete(route('admin.rooms.destroy', 999));
        $response->assertNotFound();
    }

    /** @test */
    public function non_admin_cannot_access_room_admin_routes()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user);
        
        $room = Room::factory()->create();

        $response = $this->get(route('admin.rooms.index'));
        $response->assertForbidden();

        $response = $this->post(route('admin.rooms.store'), ['name' => 'Test Room']);
        $response->assertForbidden();

        $response = $this->get(route('admin.rooms.edit', $room->id));
        $response->assertForbidden();

        $response = $this->put(route('admin.rooms.update', $room->id), ['name' => 'Updated']);
        $response->assertForbidden();

        $response = $this->delete(route('admin.rooms.destroy', $room->id));
        $response->assertForbidden();
    }

    /** @test */
    public function unauthenticated_users_cannot_access_room_admin_routes()
    {
        $room = Room::factory()->create();

        $response = $this->get(route('admin.rooms.index'));
        $response->assertRedirect('/auth/login');

        $response = $this->post(route('admin.rooms.store'), ['name' => 'Test']);
        $response->assertRedirect('/auth/login');

        $response = $this->get(route('admin.rooms.edit', $room->id));
        $response->assertRedirect('/auth/login');
    }
}