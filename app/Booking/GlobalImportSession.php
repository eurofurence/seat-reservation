<?php

namespace App\Booking;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Seat;
use Illuminate\Support\Facades\DB;

class GlobalImportSession
{
    public function __construct(
        private ImportProposalBuilder $proposals,
        private ImportBookingWriter $writer,
        private SeatAssigner $seats,
    ) {}

    /** Drop every session key that tracks an in-progress global import. */
    private function forgetQueue(): void
    {
        session()->forget(['global_import_queue', 'global_import_total', 'global_import_done', 'staged_import']);
    }

    /**
     * After a single event's import is confirmed, advance to the next queued event's
     * proposal if this is part of a global (cross-event) import, else fall back to the
     * ordinary single-event redirect.
     */
    public function advanceQueue(int $justConfirmedEventId, string $summary)
    {
        $queue = session()->get('global_import_queue');

        if ($queue === null) {
            return redirect()->route('admin.events.show', $justConfirmedEventId)
                ->with('success', $summary);
        }

        $total = session()->get('global_import_total', count($queue) + 1);
        $done = session()->get('global_import_done', 1);

        if (empty($queue)) {
            // Whole queue confirmed - write every staged event's bookings atomically.
            return $this->flushStaged($total);
        }

        $next = array_shift($queue);
        $nextEvent = Event::with('room')->find($next['event_id']);

        if (! $nextEvent) {
            $this->forgetQueue();

            return redirect()->route('admin.events.index')
                ->with('warning', 'A queued event no longer exists, so the import was cancelled and nothing was booked.');
        }

        $result = $this->proposals->build($nextEvent, $next['rows']);

        if ($result['error']) {
            $this->forgetQueue();

            return redirect()->route('admin.events.index')
                ->with('warning', "The import was cancelled and nothing was booked. Stopped at '{$nextEvent->name}': {$result['error']}");
        }

        session()->put("import_proposal:{$nextEvent->id}", $result['guests']);
        session()->put('global_import_queue', $queue);
        session()->put('global_import_done', $done + 1);

        return redirect()->route('admin.events.import-bookings.preview', $nextEvent->id)
            ->with('success', "{$summary} Continuing with '{$nextEvent->name}' (".($done + 1)." of {$total}).");
    }

    /**
     * Final step of a global import: write every staged event's bookings in one
     * transaction, all-or-nothing. A staged seat booked by someone else in the meantime is
     * auto-reassigned; if the room can no longer fit an event's guests the whole import
     * is rolled back.
     */
    private function flushStaged(int $total)
    {
        $staged = session()->get('staged_import', []);

        if (empty($staged)) {
            $this->forgetQueue();

            return redirect()->route('admin.events.index')
                ->with('warning', 'The import finished but there was nothing to book.');
        }

        $totalBooked = 0;
        $reassignedNames = [];
        $failure = null;

        try {
            DB::transaction(function () use ($staged, &$totalBooked, &$reassignedNames, &$failure) {
                foreach ($staged as $eventId => $entry) {
                    $guests = $entry['guests'];
                    $event = Event::with('room')->find($eventId);

                    if (! $event || ! $event->room) {
                        $failure = 'A queued event no longer exists, so nothing was imported.';
                        throw new \RuntimeException('flush-abort');
                    }

                    // ponytail: bounded retry, cap 3 rounds so a pathological race can't
                    // loop forever - aborts the whole flush if still unclean after that.
                    $maxRounds = 3;
                    for ($round = 0; $round < $maxRounds; $round++) {
                        $seatIds = collect($guests)->pluck('seat_ids')->flatten()->unique()->values()->all();
                        // Deterministic order avoids cross-import lock cycles.
                        sort($seatIds);
                        Seat::whereIn('id', $seatIds)->lockForUpdate()->get();

                        $conflicts = Booking::where('event_id', $eventId)
                            ->whereIn('seat_id', $seatIds)
                            ->pluck('seat_id')
                            ->all();

                        if (empty($conflicts)) {
                            break;
                        }

                        $reassigned = $this->seats->reassignConflictedGuests($event, $guests, $conflicts);

                        if ($reassigned === null) {
                            $failure = "Seats for '{$event->name}' were taken during the import and could not be reassigned. Nothing was imported - please re-run it.";
                            throw new \RuntimeException('flush-abort');
                        }

                        [$guests, $names] = $reassigned;
                        foreach ($names as $name) {
                            $reassignedNames[] = "{$event->name}: {$name}";
                        }
                    }

                    // If we exited the loop without breaking, the last iteration still had
                    // conflicts on freshly-locked seats - abort rather than double-book.
                    if (! empty($conflicts ?? [])) {
                        $failure = "Seats for '{$event->name}' kept being taken during the import. Nothing was imported - please re-run it.";
                        throw new \RuntimeException('flush-abort');
                    }

                    // Renames/deletions for already-booked guests are staged right alongside
                    // the bookable guests (see ConfirmImportController) - applied here, in
                    // the same all-or-nothing transaction as the inserts below, instead of
                    // when each event was individually confirmed.
                    foreach ($entry['renames'] as $rename) {
                        Booking::whereIn('id', $rename['booking_ids'])->update(['name' => $rename['name'], 'comment' => $rename['comment']]);
                    }

                    if ($entry['deletes'] !== []) {
                        Booking::whereIn('id', $entry['deletes'])->delete();
                    }

                    Booking::insert($this->writer->rows($eventId, $guests, $entry['created_by_name'] ?? null));
                    $totalBooked += count(collect($guests)->pluck('seat_ids')->flatten());
                }
            });
        } catch (\RuntimeException $e) {
            if ($failure !== null) {
                // Handled abort: the transaction rolled back on purpose and nothing was
                // booked, so drop the staged state - the import must be re-run from CSV.
                $this->forgetQueue();

                return redirect()->route('admin.events.index')->with('error', $failure);
            }

            // Unhandled (deadlock / transient DB error): keep the staged queue and reviewed
            // assignments intact so the flush can be retried instead of being lost.
            throw $e;
        }

        // Only clear the staged state once the whole cross-event import is safely committed.
        $this->forgetQueue();

        $summary = "Global import complete: {$total} event(s), {$totalBooked} booking(s) created.";

        if (! empty($reassignedNames)) {
            $summary .= ' Some seats were taken during the import, so these were reassigned: '.implode(', ', $reassignedNames).'.';
        }

        return redirect()->route('admin.events.index')->with('success', $summary);
    }
}
