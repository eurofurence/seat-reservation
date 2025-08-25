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

class RoomLayoutController2Test extends TestCase
{
    use RefreshDatabase, WithFaker;

    private $admin;
    private $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->room = Room::factory()->create();
    }

    /** @test */
    public function admin_can_view_layout_editor_with_existing_blocks()
    {
        $this->actingAs($this->admin);

        // Create multiple types of blocks with different positions
        $seatingBlock1 = Block::factory()->seating()->create([
            'room_id' => $this->room->id,
            'name' => 'VIP Section',
            'position_x' => 5,
            'position_y' => 3,
            'rotation' => 90,
            'order' => 1,
        ]);
        
        $seatingBlock2 = Block::factory()->seating()->create([
            'room_id' => $this->room->id,
            'name' => 'General Seating',
            'position_x' => 2,
            'position_y' => 8,
            'rotation' => 0,
            'order' => 2,
        ]);

        $stageBlock = Block::factory()->stage()->create([
            'room_id' => $this->room->id,
            'name' => 'Main Stage',
            'position_x' => 10,
            'position_y' => 1,
            'order' => 0,
        ]);

        // Add some rows and seats for counting
        $row1 = Row::factory()->create(['block_id' => $seatingBlock1->id, 'name' => 'Row A']);
        $row2 = Row::factory()->create(['block_id' => $seatingBlock1->id, 'name' => 'Row B']);
        Seat::factory()->count(3)->create(['row_id' => $row1->id]);
        Seat::factory()->count(5)->create(['row_id' => $row2->id]);

        $response = $this->get(route('admin.rooms.layout', $this->room->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/RoomLayout/Edit')
            ->where('room.id', $this->room->id)
            ->where('title', 'Floor Plan Editor')
            ->has('blocks', 2) // Should have 2 seating blocks
            ->has('stageBlocks', 1) // Should have 1 stage block
            ->where('blocks.0.name', 'VIP Section')
            ->where('blocks.0.total_seats', 8) // 3 + 5 seats
            ->where('stageBlocks.0.name', 'Main Stage')
        );
    }

    /** @test */
    public function layout_editor_migrates_old_stage_coordinates()
    {
        $this->actingAs($this->admin);
        
        // Set old stage_x/stage_y on room but no stage blocks exist
        $this->room->update(['stage_x' => 7, 'stage_y' => 4]);

        $response = $this->get(route('admin.rooms.layout', $this->room->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('stageBlocks', 1)
            ->where('stageBlocks.0.name', 'Stage')
            ->where('stageBlocks.0.position_x', 7)
            ->where('stageBlocks.0.position_y', 4)
        );

        // Verify stage block was created in database
        $this->assertDatabaseHas('blocks', [
            'room_id' => $this->room->id,
            'type' => 'stage',
            'name' => 'Stage',
            'position_x' => 7,
            'position_y' => 4,
        ]);
    }

    /** @test */
    public function admin_can_update_room_layout_with_complex_changes()
    {
        $this->actingAs($this->admin);

        // Create existing blocks
        $existingBlock = Block::factory()->seating()->create([
            'room_id' => $this->room->id,
            'name' => 'Old Block',
            'position_x' => 1,
            'position_y' => 1,
            'rotation' => 0,
        ]);

        $existingStage = Block::factory()->stage()->create([
            'room_id' => $this->room->id,
            'name' => 'Old Stage',
            'position_x' => 2,
            'position_y' => 2,
        ]);

        // Create complex row structure
        $oldRow1 = Row::factory()->create(['block_id' => $existingBlock->id, 'name' => 'Old Row 1']);
        $oldRow2 = Row::factory()->create(['block_id' => $existingBlock->id, 'name' => 'Old Row 2']);
        Seat::factory()->count(3)->create(['row_id' => $oldRow1->id]);
        Seat::factory()->count(2)->create(['row_id' => $oldRow2->id]);

        $updateData = [
            'stageBlocks' => [
                [
                    'id' => $existingStage->id,
                    'name' => 'Updated Main Stage',
                    'position_x' => 8,
                    'position_y' => 1,
                ]
            ],
            'blocks' => [
                [
                    'id' => $existingBlock->id,
                    'name' => 'Premium Section',
                    'position_x' => 3,
                    'position_y' => 5,
                    'rotation' => 180,
                    'rowsData' => [
                        ['rowNumber' => 1, 'seatCount' => 12, 'isCustom' => false],
                        ['rowNumber' => 2, 'seatCount' => 10, 'isCustom' => true],
                        ['rowNumber' => 3, 'seatCount' => 15, 'isCustom' => false],
                    ],
                ]
            ],
        ];

        $response = $this->put(route('admin.rooms.layout.update', $this->room->id), $updateData);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Room layout updated successfully!');

        // Verify stage block update
        $this->assertDatabaseHas('blocks', [
            'id' => $existingStage->id,
            'name' => 'Updated Main Stage',
            'position_x' => 8,
            'position_y' => 1,
        ]);

        // Verify seating block update
        $this->assertDatabaseHas('blocks', [
            'id' => $existingBlock->id,
            'name' => 'Premium Section',
            'position_x' => 3,
            'position_y' => 5,
            'rotation' => 180,
        ]);

        // Verify old rows were deleted
        $this->assertDatabaseMissing('rows', ['id' => $oldRow1->id]);
        $this->assertDatabaseMissing('rows', ['id' => $oldRow2->id]);

        // Verify new rows were created
        $this->assertDatabaseHas('rows', [
            'block_id' => $existingBlock->id,
            'name' => 'Row 1',
            'seats_count' => 12,
            'custom_seat_count' => null,
        ]);

        $this->assertDatabaseHas('rows', [
            'block_id' => $existingBlock->id,
            'name' => 'Row 2',
            'seats_count' => 10,
            'custom_seat_count' => 10, // Custom seat count
        ]);

        // Verify total seat count (12 + 10 + 15 = 37)
        $totalSeats = Seat::whereHas('row', function($query) use ($existingBlock) {
            $query->where('block_id', $existingBlock->id);
        })->count();
        $this->assertEquals(37, $totalSeats);
    }

    /** @test */
    public function layout_update_creates_seats_with_correct_labels()
    {
        $this->actingAs($this->admin);

        $block = Block::factory()->seating()->create(['room_id' => $this->room->id]);

        $updateData = [
            'stageBlocks' => [],
            'blocks' => [
                [
                    'id' => $block->id,
                    'name' => $block->name,
                    'position_x' => $block->position_x,
                    'position_y' => $block->position_y,
                    'rotation' => $block->rotation,
                    'rowsData' => [
                        ['rowNumber' => 1, 'seatCount' => 5, 'isCustom' => false],
                    ],
                ]
            ],
        ];

        $response = $this->put(route('admin.rooms.layout.update', $this->room->id), $updateData);

        $response->assertRedirect();

        // Verify seats have correct labels (A, B, C, D, E for 5 seats)
        $this->assertDatabaseHas('seats', ['label' => 'A', 'number' => 1]);
        $this->assertDatabaseHas('seats', ['label' => 'B', 'number' => 2]);
        $this->assertDatabaseHas('seats', ['label' => 'C', 'number' => 3]);
        $this->assertDatabaseHas('seats', ['label' => 'D', 'number' => 4]);
        $this->assertDatabaseHas('seats', ['label' => 'E', 'number' => 5]);
    }

    /** @test */
    public function admin_can_create_seating_block_with_default_settings()
    {
        $this->actingAs($this->admin);

        // Get initial block count
        $initialBlockCount = Block::where('room_id', $this->room->id)->count();

        $response = $this->post(route('admin.rooms.blocks.create', $this->room->id), [
            'name' => 'New Premium Block',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Block created successfully!');

        $this->assertDatabaseHas('blocks', [
            'room_id' => $this->room->id,
            'name' => 'New Premium Block',
            'type' => 'seating',
            'position_x' => 0,
            'position_y' => 0,
            'rotation' => 0,
        ]);

        // Verify block count increased
        $this->assertEquals($initialBlockCount + 1, Block::where('room_id', $this->room->id)->count());
    }

    /** @test */
    public function block_creation_sets_correct_order_sequence()
    {
        $this->actingAs($this->admin);

        // Create some existing blocks with specific orders
        Block::factory()->seating()->create(['room_id' => $this->room->id, 'order' => 2]);
        Block::factory()->seating()->create(['room_id' => $this->room->id, 'order' => 5]);
        Block::factory()->seating()->create(['room_id' => $this->room->id, 'order' => 1]);

        $response = $this->post(route('admin.rooms.blocks.create', $this->room->id), [
            'name' => 'Latest Block',
        ]);

        $response->assertRedirect();

        // New block should get order = max(existing orders) + 1 = 6
        $newBlock = Block::where('name', 'Latest Block')->first();
        $this->assertEquals(6, $newBlock->order);
    }

    /** @test */
    public function admin_can_delete_seating_block_and_cascade()
    {
        $this->actingAs($this->admin);
        
        $block = Block::factory()->seating()->create(['room_id' => $this->room->id]);
        $row1 = Row::factory()->create(['block_id' => $block->id]);
        $row2 = Row::factory()->create(['block_id' => $block->id]);
        $seat1 = Seat::factory()->create(['row_id' => $row1->id]);
        $seat2 = Seat::factory()->create(['row_id' => $row1->id]);
        $seat3 = Seat::factory()->create(['row_id' => $row2->id]);

        $response = $this->delete(route('admin.rooms.blocks.delete', ['room' => $this->room->id, 'block' => $block->id]));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Block deleted successfully!');

        // Verify complete cascade deletion
        $this->assertDatabaseMissing('blocks', ['id' => $block->id]);
        $this->assertDatabaseMissing('rows', ['id' => $row1->id]);
        $this->assertDatabaseMissing('rows', ['id' => $row2->id]);
        $this->assertDatabaseMissing('seats', ['id' => $seat1->id]);
        $this->assertDatabaseMissing('seats', ['id' => $seat2->id]);
        $this->assertDatabaseMissing('seats', ['id' => $seat3->id]);
    }

    /** @test */
    public function cannot_delete_block_from_different_room()
    {
        $this->actingAs($this->admin);
        
        $otherRoom = Room::factory()->create();
        $otherBlock = Block::factory()->seating()->create(['room_id' => $otherRoom->id]);

        $response = $this->delete(route('admin.rooms.blocks.delete', ['room' => $this->room->id, 'block' => $otherBlock->id]));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Block not found.');

        // Block should still exist
        $this->assertDatabaseHas('blocks', ['id' => $otherBlock->id]);
    }

    /** @test */
    public function layout_validation_enforces_block_requirements()
    {
        $this->actingAs($this->admin);

        $block = Block::factory()->seating()->create(['room_id' => $this->room->id]);

        $invalidData = [
            'stageBlocks' => [
                [
                    'id' => null,
                    'name' => '', // Required field empty
                    'position_x' => -2, // Invalid position
                    'position_y' => 0,
                ]
            ],
            'blocks' => [
                [
                    'id' => $block->id,
                    'name' => '', // Required field empty
                    'position_x' => -5, // Invalid position
                    'position_y' => 0,
                    'rotation' => 45, // Invalid rotation
                ]
            ],
        ];

        $response = $this->put(route('admin.rooms.layout.update', $this->room->id), $invalidData);

        $response->assertSessionHasErrors([
            'stageBlocks.0.name',
            'stageBlocks.0.position_x', 
            'blocks.0.name',
            'blocks.0.position_x',
            'blocks.0.rotation'
        ]);
    }

    /** @test */
    public function layout_validation_enforces_row_data_constraints()
    {
        $this->actingAs($this->admin);

        $block = Block::factory()->seating()->create(['room_id' => $this->room->id]);

        $invalidData = [
            'stageBlocks' => [],
            'blocks' => [
                [
                    'id' => $block->id,
                    'name' => $block->name,
                    'position_x' => 0,
                    'position_y' => 0,
                    'rotation' => 0,
                    'rowsData' => [
                        ['rowNumber' => 0, 'seatCount' => 0], // Both invalid (too low)
                        ['rowNumber' => 51, 'seatCount' => 101], // Both invalid (too high)
                    ],
                ]
            ],
        ];

        $response = $this->put(route('admin.rooms.layout.update', $this->room->id), $invalidData);

        $response->assertSessionHasErrors([
            'blocks.0.rowsData.0.rowNumber',
            'blocks.0.rowsData.0.seatCount',
            'blocks.0.rowsData.1.rowNumber',
            'blocks.0.rowsData.1.seatCount',
        ]);
    }
}