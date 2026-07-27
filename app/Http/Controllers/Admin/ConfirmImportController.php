<?php

namespace App\Http\Controllers\Admin;

use App\Booking\GlobalImportSession;
use App\Booking\ImportBookingWriter;
use App\Booking\SeatAssigner;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Seat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfirmImportController extends Controller
{
    public function __invoke(Request $request, $id, ImportBookingWriter $writer, SeatAssigner $seats, GlobalImportSession $global)
    {
        $data = $request->validate([
            'guests' => 'required|array|min:1',
            'guests.*.guest_name' => 'required|string|max:255',
            'guests.*.original_guest_name' => 'nullable|string|max:255',
            'guests.*.comment' => 'nullable|string|max:1000',
            'guests.*.skipped' => 'sometimes|boolean',
            // Enforced conditionally in the loop below instead of here - a skipped guest is
            // allowed to carry zero seats since they aren't being booked this run at all.
            'guests.*.seat_ids' => 'present|array',
            'guests.*.seat_ids.*' => 'required|integer|exists:seats,id',
        ]);

        $event = Event::with('room')->findOrFail($id);
        $guests = $data['guests'];

        // Gate against booking fewer seats than the CSV requested for any guest. The
        // required minimum comes from the session's canonical proposal (not the client's
        // payload) so it can't be tampered with by editing the posted request.
        $sessionProposal = session()->get("import_proposal:{$id}");

        // Reject confirmations without a pending proposal - otherwise a client that has lost
        // (or never had) session state can POST directly and skip the whole quota/alignment
        // gate, inserting whatever seat_ids it pleases.
        if (! is_array($sessionProposal) || $sessionProposal === []) {
            return redirect()->route('admin.events.show', $id)
                ->with('error', 'No pending import found. Please upload the CSV again.');
        }

        // The preview posts guests in the same order as the session proposal and never
        // adds, removes, or reorders them, so the index->quota mapping below is only
        // trustworthy while the posted guests still line up with the proposal. guest_name
        // is editable on the review screen, so alignment is pinned to the untouched
        // original_guest_name the client echoes back (falling back to guest_name when it's
        // absent, e.g. older/programmatic callers that don't rename). A count or name
        // mismatch means the payload was tampered with (e.g. guests reordered or inserted
        // to swap quotas), so reject rather than risk booking fewer seats than requested
        // for the wrong guest.
        if (count($guests) !== count($sessionProposal)) {
            return back()->with('error', 'This import is out of sync with your session (it may have expired or changed in another tab). Please refresh the page and try again.');
        }

        foreach ($guests as $index => $guest) {
            if (($sessionProposal[$index]['guest_name'] ?? null) !== ($guest['original_guest_name'] ?? $guest['guest_name'])) {
                return back()->with('error', 'This import is out of sync with your session (it may have expired or changed in another tab). Please refresh the page and try again.');
            }

            // A skipped guest isn't being booked this run at all, so the quota gate doesn't
            // apply to them - they're excluded from booking entirely below instead.
            if (! empty($guest['skipped'])) {
                continue;
            }

            $required = $sessionProposal[$index]['requested_seats'] ?? 1;
            $selected = count($guest['seat_ids']);

            if ($selected < $required) {
                return back()->with('error', "{$guest['guest_name']} needs at least {$required} seat(s) but only {$selected} ".($selected === 1 ? 'is' : 'are').' selected. Please pick more seats before confirming.');
            }
        }

        // Guests marked skipped on the review screen aren't processed this run at all - no
        // insert, no rename, no seat reconciliation for an already-booked one. Nothing is
        // written for them, so re-uploading the same CSV later naturally reprocesses them fresh.
        $skippedCount = count(array_filter($guests, fn ($guest) => ! empty($guest['skipped'])));

        // Guests the proposal flagged as already booked on this event (a re-imported CSV
        // recognizing someone it - or a manual/self-service booking - already placed) aren't
        // re-inserted from scratch - they're reconciled against their existing booking rows
        // instead, since this CSV (or the admin, on the review screen) may now want a
        // different seat count than they already have:
        //   - seats no longer in their posted seat_ids are freed (their booking row deleted)
        //   - seats newly added go through the normal insert pipeline below, alongside
        //     brand-new guests (conflict detection, room-ownership check, the writer)
        //   - an edited guest_name/comment is pushed back onto the rows that remain theirs
        //     (a bulk rename) instead of being silently discarded
        // Existing booking/seat ids are trusted from the session proposal, not the posted
        // payload, for the same tamper-proofing reason as requested_seats above. Original
        // array keys are kept (not reindexed) so reassignConflictedGuests's index-based
        // bookkeeping still lines up.
        $bookableGuests = [];
        $alreadyBookedCount = 0;
        $renamedCount = 0;
        $addedSeatCount = 0;
        $removedBookingIds = [];
        $keptSeatIdsByIndex = [];
        // Queued instead of written immediately - applied only once every gate below has
        // passed (see the transaction/staging further down), so an early return never
        // leaves a rename/deletion committed with nothing booked in exchange.
        $pendingRenames = [];

        foreach ($guests as $index => $guest) {
            if (! empty($guest['skipped'])) {
                continue;
            }

            if (! empty($sessionProposal[$index]['already_booked'])) {
                $alreadyBookedCount++;

                $existingBookings = $sessionProposal[$index]['existing_bookings'] ?? [];
                $existingSeatIds = array_column($existingBookings, 'seat_id');
                $seatIdToBookingId = array_column($existingBookings, 'id', 'seat_id');
                $newSeatIds = $guest['seat_ids'];

                $toRemove = array_diff($existingSeatIds, $newSeatIds);
                $toAdd = array_values(array_diff($newSeatIds, $existingSeatIds));
                $toKeep = array_diff($existingSeatIds, $toRemove);

                foreach ($toRemove as $seatId) {
                    $removedBookingIds[] = $seatIdToBookingId[$seatId];
                }

                $newName = trim($guest['guest_name']);
                $newComment = $guest['comment'] !== null && trim($guest['comment']) !== '' ? $guest['comment'] : null;
                $originalName = $sessionProposal[$index]['guest_name'] ?? $newName;
                $originalComment = $sessionProposal[$index]['comment'] ?? null;

                $keepBookingIds = array_map(fn ($seatId) => $seatIdToBookingId[$seatId], $toKeep);
                if ($keepBookingIds !== [] && ($newName !== $originalName || $newComment !== $originalComment)) {
                    $pendingRenames[] = ['booking_ids' => $keepBookingIds, 'name' => $newName, 'comment' => $newComment];
                    $renamedCount++;
                }

                if ($toAdd !== []) {
                    $bookableGuests[$index] = ['guest_name' => $newName, 'comment' => $newComment, 'seat_ids' => $toAdd];
                    $addedSeatCount += count($toAdd);
                    $keptSeatIdsByIndex[$index] = array_values($toKeep);
                }

                continue;
            }

            $bookableGuests[$index] = $guest;
        }

        $flatSeatIds = collect($bookableGuests)->pluck('seat_ids')->flatten();
        $allSeatIds = $flatSeatIds->unique()->values()->all();

        // Reject the same seat being handed to two guests (or listed twice for one guest):
        // it would insert duplicate bookings for a single seat and silently double-book it.
        if ($flatSeatIds->count() !== count($allSeatIds)) {
            return back()->with('error', 'The same seat was assigned more than once. Please review the seat picks and try again.');
        }

        // Every seat must belong to this event's room - a tampered payload could otherwise
        // book seats from another room against this event and corrupt the seating data.
        $roomSeatCount = Seat::whereHas('row.block', fn ($q) => $q->where('room_id', $event->room_id))
            ->whereIn('id', $allSeatIds)
            ->count();

        if ($roomSeatCount !== count($allSeatIds)) {
            return back()->with('error', "One or more selected seats don't belong to this event's room.");
        }

        $conflictSeatIds = [];

        // Single-event imports write immediately on confirm. A global (cross-event) import
        // instead STAGES each confirmed event in the session and writes everything in one
        // atomic transaction only when the whole queue is done (see GlobalImportSession) - so
        // abandoning a global import half-way leaves nothing booked at all.
        $isGlobal = session()->has('global_import_queue');

        DB::transaction(function () use ($id, $bookableGuests, $allSeatIds, $isGlobal, $writer, $pendingRenames, $removedBookingIds, &$conflictSeatIds) {
            Seat::whereIn('id', $allSeatIds)->lockForUpdate()->get();

            $conflictSeatIds = Booking::where('event_id', $id)
                ->whereIn('seat_id', $allSeatIds)
                ->pluck('seat_id')
                ->all();

            if (empty($conflictSeatIds) && ! $isGlobal) {
                foreach ($pendingRenames as $rename) {
                    Booking::whereIn('id', $rename['booking_ids'])->update(['name' => $rename['name'], 'comment' => $rename['comment']]);
                }

                if ($removedBookingIds !== []) {
                    Booking::whereIn('id', $removedBookingIds)->delete();
                }

                Booking::insert($writer->rows($id, $bookableGuests));
            }
        });

        if (empty($conflictSeatIds)) {
            session()->forget("import_proposal:{$id}");

            $alreadyBookedNote = '';
            if ($alreadyBookedCount > 0) {
                $alreadyBookedNote = " Matched {$alreadyBookedCount} already-booked guest(s).";
                if ($renamedCount > 0) {
                    $alreadyBookedNote .= " Renamed {$renamedCount}.";
                }
                if ($addedSeatCount > 0) {
                    $alreadyBookedNote .= " Added {$addedSeatCount} seat(s) for them.";
                }
                if ($removedBookingIds !== []) {
                    $alreadyBookedNote .= ' Freed '.count($removedBookingIds).' seat(s) for them.';
                }
            }
            if ($skippedCount > 0) {
                $alreadyBookedNote .= " Skipped {$skippedCount} guest(s) - they'll be picked up on the next import.";
            }

            if ($isGlobal) {
                // Nothing is written for a global import until the whole queue is confirmed
                // - stage the rename/delete instructions alongside the bookable guests so
                // GlobalImportSession::flushStaged() can apply them atomically with everyone
                // else's bookings (see the transaction above for the non-global equivalent).
                $staged = session()->get('staged_import', []);
                $staged[$id] = [
                    'guests' => $bookableGuests,
                    'renames' => $pendingRenames,
                    'deletes' => $removedBookingIds,
                ];
                session()->put('staged_import', $staged);

                $summary = 'Staged '.count($allSeatIds).' booking(s) for '.count($bookableGuests).' guest(s).'.$alreadyBookedNote;
            } else {
                $summary = 'Successfully imported '.count($allSeatIds).' booking(s) for '.count($bookableGuests).' guest(s).'.$alreadyBookedNote;
            }

            return $global->advanceQueue($id, $summary);
        }

        // Some seats were booked by someone else in the meantime — reassign only the
        // affected guest(s), leave everyone else's confirmed-good assignment untouched. Same
        // routine GlobalImportSession's flush uses; it marks each moved guest's
        // fallback_level_used as 'room' (whole-room scan) so a stale preview badge can't
        // claim a row/block placement that's no longer true.
        $reassigned = $seats->reassignConflictedGuests($event, $bookableGuests, $conflictSeatIds);

        if ($reassigned === null) {
            return back()->with('error', 'Could not reassign one or more guests — not enough seats remain available. Please adjust the import manually.');
        }

        [$bookableGuests, $affectedNames] = $reassigned;

        // Merge the reassigned bookable guests back into the full (original-index) list so
        // the redisplayed preview still includes the already-booked entries, unchanged. An
        // already-booked guest that had seats added only carries its ADDED seats in
        // $bookableGuests (the kept ones were never touched) - stitch those kept seats back
        // in so the redisplay shows their full set, not just the newly (re)assigned ones.
        foreach ($keptSeatIdsByIndex as $index => $keptSeatIds) {
            if (isset($bookableGuests[$index])) {
                $bookableGuests[$index]['seat_ids'] = array_merge($keptSeatIds, $bookableGuests[$index]['seat_ids']);
            }
        }

        $guests = array_replace($guests, $bookableGuests);

        // The validated request payload only carries guest_name/comment/seat_ids - carry the
        // canonical requested_seats quota (and the preference/timestamp/fallback/already_booked
        // metadata used for the preview badges) back over from the prior session proposal so
        // the minimum-seats gate still applies on the next confirm attempt and the badges don't
        // vanish after a concurrency reassignment. fallback_level_used is intentionally NOT
        // carried back over: reassignConflictedGuests above already set it to 'room' for the
        // affected guests, and copying the prior value would revert that to the stale
        // row/block placement the concurrency reassignment invalidated.
        foreach ($guests as $index => &$guest) {
            $prior = $sessionProposal[$index] ?? [];
            $guest['requested_seats'] = $prior['requested_seats'] ?? count($guest['seat_ids']);
            foreach (['assignment_strategy', 'preferred_block_name', 'preferred_row_name', 'submission_timestamp', 'unresolved', 'unresolved_reason', 'already_booked', 'seat_count_mismatch', 'csv_requested_seats', 'existing_seat_count', 'existing_bookings'] as $carry) {
                if (array_key_exists($carry, $prior)) {
                    $guest[$carry] = $prior[$carry];
                }
            }
            if (! array_key_exists('fallback_level_used', $guest) && array_key_exists('fallback_level_used', $prior)) {
                $guest['fallback_level_used'] = $prior['fallback_level_used'];
            }
        }
        unset($guest);

        session()->put("import_proposal:{$id}", $guests);

        $names = implode(', ', $affectedNames);

        return redirect()->route('admin.events.import-bookings.preview', $id)
            ->with('warning', "Some seats were just booked by someone else. Reassigned seats for: {$names}. Please review before confirming again.");
    }
}
