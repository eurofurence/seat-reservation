<?php

namespace App\Observers;

use App\Models\Block;
use App\Models\Row;

class RowObserver
{
    public function created(Row $row): void
    {
        $this->updateSeats($row);
    }

    public function updated(Row $row): void
    {
        $this->updateSeats($row);
    }

    public function deleted(Row $row): void
    {
    }

    public function restored(Row $row): void
    {
    }

    private function updateSeats(Row $row)
    {
        if ($row->isDirty('seat_count')) {
            $currentSeatsCount = $row->seats()->count();
            $newSeatsCount = $row->seat_count;
            if ($currentSeatsCount < $newSeatsCount) {
                for ($i = $currentSeatsCount; $i < $newSeatsCount; $i++) {
                    // Seats are Numbered A-Z
                    $row->seats()->create([
                        'name' => chr(65 + $i),
                    ]);
                }
            } elseif ($currentSeatsCount > $newSeatsCount) {
                $row->seats()->orderBy('name', 'desc')->limit($currentSeatsCount - $newSeatsCount)->delete();
            }
        }
    }
}
