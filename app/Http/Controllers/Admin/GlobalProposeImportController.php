<?php

namespace App\Http\Controllers\Admin;

use App\Booking\EventMatcher;
use App\Booking\ImportCsvParser;
use App\Booking\ImportProposalBuilder;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Cross-event import: one CSV with an extra `Event` column, covering multiple events at
 * once (e.g. a single shared Nextcloud Forms sign-up used for every event). Processed one
 * event at a time, reusing the exact same per-event propose/preview/confirm flow - this
 * just builds the first event's proposal and stashes the rest of the queue in the session
 * for ConfirmImportController/GlobalImportSession to advance through.
 */
class GlobalProposeImportController extends Controller
{
    public function __invoke(Request $request, ImportCsvParser $parser, EventMatcher $matcher, ImportProposalBuilder $proposals)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $rows = $parser->parse($request->file('file'), requireEvent: true);

        if (is_string($rows)) {
            return back()->with('error', $rows);
        }

        $queue = $matcher->groupRowsByEvent($rows);

        if (is_string($queue)) {
            return back()->with('error', $queue);
        }

        // Hard-fail row checks (blank Guest Name, non-numeric Number of Seats) must reject
        // the whole upload upfront, same as a missing Event - not only once the admin has
        // already reviewed/confirmed earlier events in the queue and reached the bad row's
        // event further down the file.
        foreach ($queue as $entry) {
            $error = $proposals->validateRows($entry['rows']);

            if ($error !== null) {
                return back()->with('error', "'{$entry['event']->name}': {$error}");
            }
        }

        $first = array_shift($queue);
        $result = $proposals->build($first['event'], $first['rows']);

        if ($result['error']) {
            return back()->with('error', $result['error']);
        }

        session()->put("import_proposal:{$first['event']->id}", $result['guests']);
        session()->put('global_import_queue', array_map(
            fn ($entry) => ['event_id' => $entry['event']->id, 'rows' => $entry['rows']],
            $queue
        ));
        session()->put('global_import_total', count($queue) + 1);
        session()->put('global_import_done', 1);
        // Start staging from scratch - drop anything left over from an abandoned prior run.
        session()->forget('staged_import');

        return redirect()->route('admin.events.import-bookings.preview', $first['event']->id);
    }
}
