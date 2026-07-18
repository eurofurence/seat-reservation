<?php

namespace App\Http\Controllers\Admin;

use App\Booking\EventMatcher;
use App\Booking\ImportCsvParser;
use App\Booking\ImportProposalBuilder;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;

class ProposeImportController extends Controller
{
    public function __invoke(Request $request, $id, ImportCsvParser $parser, ImportProposalBuilder $proposals, EventMatcher $matcher)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $event = Event::with('room')->findOrFail($id);

        $rows = $parser->parse($request->file('file'));

        if (is_string($rows)) {
            return back()->with('error', $rows);
        }

        // The `Event` column is optional here (unlike global import, where it's mandatory).
        // When a row has one filled in, honor it: only rows left blank or matching this
        // event's name (soft-matched, same rule as global import) belong in this import -
        // rows naming a different event are dropped so a CSV that also covers other events
        // doesn't silently book seats onto this one.
        $skipped = 0;
        $rows = array_values(array_filter($rows, function ($row) use ($event, $matcher, &$skipped) {
            if ($row['event'] === '' || $matcher->matches($row['event'], $event->name)) {
                return true;
            }

            $skipped++;

            return false;
        }));

        if ($rows === []) {
            return back()->with('error', "No rows in this file match event '{$event->name}'. Check the CSV's Event column.");
        }

        $result = $proposals->build($event, $rows);

        if ($result['error']) {
            return back()->with('error', $result['error']);
        }

        // A single-event import writes immediately on confirm, so make sure no leftover
        // global-import state from an abandoned cross-event run is still around - otherwise
        // confirmImport would mistake this for a global import and stage instead of book.
        session()->forget(['global_import_queue', 'global_import_total', 'global_import_done', 'staged_import']);

        session()->put("import_proposal:{$id}", $result['guests']);

        $redirect = redirect()->route('admin.events.import-bookings.preview', $id);

        if ($skipped > 0) {
            $redirect->with('info', "Skipped {$skipped} row(s) belonging to a different event.");
        }

        return $redirect;
    }
}
