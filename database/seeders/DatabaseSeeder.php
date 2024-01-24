<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create an Room and an Event for Eurofurence
        $room = \App\Models\Room::create(['name' => 'CCH Hall 1']);
        $event = \App\Models\Event::create(['name' => 'Eurofurence 2024', 'room_id' => $room->id,'starts_at' => now()->addDay()->addHour(),'reservation_ends_at' => now()->addDay()]);

        // Create two blocks with 8 rows and 12 seats each
        $blocks = [[
            "name" => "Left Block",
        ],[
            "name" => "Right Block",
        ]];
        foreach($blocks as $block) {
            $block = \App\Models\Block::create(['name' => $block['name'], 'room_id' => $room->id]);
            for($i = 1; $i <= 4; $i++) {
                $row = \App\Models\Row::create(['name' => $i, 'block_id' => $block->id]);
                for($j = 1; $j <= 6; $j++) {
                    $seat = \App\Models\Seat::create(['name' => $j, 'row_id' => $row->id]);
                }
            }
        }


    }
}
