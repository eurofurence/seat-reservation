<?php

namespace App\Http\Controllers\Admin;

use App\Booking\ImportSessionStore;
use App\Booking\RoomLayoutLoader;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Inertia\Inertia;

class ImportPreviewController extends Controller
{
    public function __invoke($id, RoomLayoutLoader $layout, ImportSessionStore $importSession)
    {
        $event = Event::select('id', 'name', 'room_id')
            ->with('room:id,name,stage_x,stage_y')
            ->findOrFail($id);

        $proposal = $importSession->get("import_proposal:{$id}");

        if (! $proposal) {
            return redirect()->route('admin.events.show', $id)
                ->with('error', 'No pending import found. Please upload a CSV first.');
        }

        [$room, $blocks, $stageBlocks, $bookedSeats] = $layout->load($event);

        // When this preview is part of a cross-event "global import", surface how far along
        // the queue we are so the admin can see how many events are left to click through.
        $progress = null;
        if ($importSession->has('global_import_total')) {
            $progress = [
                'done' => $importSession->get('global_import_done', 1),
                'total' => $importSession->get('global_import_total'),
            ];
        }

        return Inertia::render('Admin/EventImport/Preview', [
            'event' => $event,
            'room' => $room,
            'blocks' => $blocks,
            'stageBlocks' => $stageBlocks,
            'bookedSeats' => $bookedSeats,
            'proposal' => $proposal,
            'progress' => $progress,
            'title' => 'Import Preview - '.$event->name,
            'breadcrumbs' => [
                ['title' => 'Events', 'url' => route('admin.events.index')],
                ['title' => $event->name, 'url' => route('admin.events.show', $event->id)],
                ['title' => 'Import Preview', 'url' => null],
            ],
        ]);
    }
}
