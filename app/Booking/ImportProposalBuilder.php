<?php

namespace App\Booking;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Room;
use App\Models\Seat;

class ImportProposalBuilder
{
    public function __construct(private SeatAssigner $seats) {}

    /**
     * Resolve exact-seat rows and auto-assign the rest. Block+Row+Seat all filled = exact
     * seat. Otherwise a preference tier:
     *   - Block + Row (Seat blank) -> row_preferred: that row, else that block
     *   - Block only               -> block_preferred: that block
     *   - Row only (Block blank)   -> row_preferred against the default "center" block
     *   - fully blank              -> none: room-wide, "center" first
     * A preference (any of the first three) that can't fit within its own area is left
     * unresolved rather than silently seated elsewhere in the room - see "Auto-place seats"
     * on the review screen for that. Only "none" scans the whole room. A room genuinely out
     * of free seats for a guest aborts the whole import; anything less never does - the
     * guest is just left unresolved for manual seat picking.
     * An optional Number of Seats/Quantity/Seats column requests several seats from one row
     * (default 1). Groups compete oldest-first by an optional submission-timestamp column;
     * groups without one go last, in CSV order.
     *
     * A guest name that already has a booking on this event is short-circuited into one
     * 'already_booked' entry (existing seats + 'existing_bookings' [id, seat_id] pairs)
     * instead of being re-assigned, so a re-imported/growing CSV doesn't double-book them.
     * requested_seats on that entry is this CSV's ask (not their current count), so the
     * normal under/over-quota UI flags a changed request; ConfirmImportController reconciles
     * against 'existing_bookings'.
     *
     * Returns ['error' => string|null, 'guests' => [['guest_name','comment','seat_ids',...], ...]].
     */
    public function build(Event $event, array $rows): array
    {
        $error = $this->validateRows($rows);

        if ($error !== null) {
            return ['error' => $error, 'guests' => []];
        }

        $room = $event->room;

        $seatLookup = [];
        Seat::whereHas('row.block', fn ($q) => $q->where('room_id', $room->id))
            ->with('row.block')
            ->get()
            ->each(function ($seat) use (&$seatLookup) {
                $key = strtolower(trim($seat->row->block->name)).'|'.strtolower(trim($seat->row->name)).'|'.strtolower(trim($seat->label));
                $seatLookup[$key] = $seat->id;
            });

        $allSeatIdsInOrder = $this->seats->orderedRoomSeatIds($room);
        $unavailable = Booking::where('event_id', $event->id)->pluck('seat_id')->all();

        // Existing [id, seat_id] pairs per guest name, across any booking on this event - lets
        // a re-imported CSV recognize an already-placed guest instead of assigning them again.
        $existingBookingsByName = Booking::where('event_id', $event->id)
            ->whereNotNull('name')
            ->get(['id', 'name', 'seat_id'])
            ->groupBy(fn ($b) => mb_strtolower(trim($b->name)))
            ->map(fn ($group) => $group->map(fn ($b) => ['id' => $b->id, 'seat_id' => $b->seat_id])->all())
            ->all();
        $existingSeatIdsByName = collect($existingBookingsByName)->map(fn ($bookings) => array_column($bookings, 'seat_id'))->all();

        // Total seats this CSV asks for per already-booked guest name, so a re-import
        // requesting a different quantity than before gets flagged instead of ignored.
        $csvRequestedByName = [];
        foreach ($rows as $row) {
            $nameKey = mb_strtolower(trim(mb_substr($row['guest name'], 0, 255)));
            if (! isset($existingSeatIdsByName[$nameKey])) {
                continue;
            }

            $rowQuantity = $this->filledSeatFieldsCount($row) === 3 ? 1 : $this->quantity($row);

            $csvRequestedByName[$nameKey] = ($csvRequestedByName[$nameKey] ?? 0) + $rowQuantity;
        }

        $guests = [];
        $autoGroups = [];
        $alreadyBookedNames = [];

        foreach ($rows as $rowIndex => $row) {
            $guestName = mb_substr($row['guest name'], 0, 255);
            $comment = $row['comment'] !== '' ? mb_substr($row['comment'], 0, 1000) : null;
            // Preview-only, never persisted or exported.
            $userDisplayName = ($row['user display name'] ?? '') !== '' ? mb_substr($row['user display name'], 0, 255) : null;
            $block = $row['block'];
            $rowName = $row['row'];
            $seatLabel = $row['seat'];
            $rowNumber = $row['_row'];
            $timestamp = $row['_timestamp'] ?? null;

            $nameKey = mb_strtolower(trim($guestName));

            $filledCount = $this->filledSeatFieldsCount($row);

            $isRowPreferred = $filledCount === 2 && $block !== '' && $rowName !== '' && $seatLabel === '';
            $isBlockOnlyPreference = $filledCount === 1 && $block !== '';
            $isRowOnlyPreference = $filledCount === 1 && $block === '' && $rowName !== '';

            if (isset($existingSeatIdsByName[$nameKey])) {
                // Collapse every row for this guest into one entry, not one per row.
                if (! isset($alreadyBookedNames[$nameKey])) {
                    $existingSeatIds = $existingSeatIdsByName[$nameKey];
                    $csvRequested = $csvRequestedByName[$nameKey] ?? count($existingSeatIds);
                    $guests[] = [
                        'guest_name' => $guestName,
                        'comment' => $comment,
                        'user_display_name' => $userDisplayName,
                        'submission_timestamp' => $timestamp,
                        'seat_ids' => $existingSeatIds,
                        // This CSV row's preference, surfaced so a still-unresolved
                        // re-imported guest shows where they want to sit.
                        'preferred_block_name' => $isRowPreferred || $isBlockOnlyPreference ? $block : null,
                        'preferred_row_name' => $isRowPreferred || $isRowOnlyPreference ? $rowName : null,
                        'requested_seats' => $csvRequested,
                        'already_booked' => true,
                        'seat_count_mismatch' => $csvRequested !== count($existingSeatIds),
                        'csv_requested_seats' => $csvRequested,
                        'existing_seat_count' => count($existingSeatIds),
                        // Confirm reconciles seat_ids against these and bulk-renames them.
                        'existing_bookings' => $existingBookingsByName[$nameKey],
                    ];
                    $alreadyBookedNames[$nameKey] = true;
                }

                continue;
            }

            if ($filledCount === 3) {
                $key = strtolower($block).'|'.strtolower($rowName).'|'.strtolower($seatLabel);
                $requestedSeat = "{$block} {$rowName} {$seatLabel}";

                if (! isset($seatLookup[$key])) {
                    // Typo'd/mismatched seat - admin picks a real one on the review page.
                    $guests[] = [
                        'guest_name' => $guestName,
                        'comment' => $comment,
                        'user_display_name' => $userDisplayName,
                        'seat_ids' => [],
                        'requested_seats' => 1,
                        'unresolved' => true,
                        'unresolved_reason' => "Seat '{$requestedSeat}' not found in this room.",
                    ];

                    continue;
                }

                $seatId = $seatLookup[$key];

                if (in_array($seatId, $unavailable, true)) {
                    $guests[] = [
                        'guest_name' => $guestName,
                        'comment' => $comment,
                        'user_display_name' => $userDisplayName,
                        'seat_ids' => [],
                        'requested_seats' => 1,
                        'unresolved' => true,
                        'unresolved_reason' => "Seat '{$requestedSeat}' is already booked or used more than once in this file.",
                    ];

                    continue;
                }

                $unavailable[] = $seatId;
                $guests[] = [
                    'guest_name' => $guestName,
                    'comment' => $comment,
                    'user_display_name' => $userDisplayName,
                    'seat_ids' => [$seatId],
                    'requested_seats' => 1,
                ];
            } else {
                // Number of Seats/Quantity/Seats column; blank defaults to 1. Non-numeric
                // values are already rejected by validateRows().
                $requestedQuantity = $this->quantity($row);

                // Structural encoding, not string concat, so a '|' in a name/comment can't
                // alias two distinct guests together.
                $groupKey = serialize([
                    mb_strtolower($guestName),
                    mb_strtolower((string) $comment),
                ]);

                if (! isset($autoGroups[$groupKey])) {
                    // First row of the group pins the strategy/preference; later rows for
                    // the same guest just add to the quantity. Row-only defaults to the
                    // "center" block; fully blank defaults to room-wide, "center" first.
                    if ($isRowPreferred) {
                        $strategy = 'row_preferred';
                        $preferredBlock = $block;
                        $preferredRow = $rowName;
                        $displayBlock = $block;
                        $displayRow = $rowName;
                    } elseif ($isBlockOnlyPreference) {
                        $strategy = 'block_preferred';
                        $preferredBlock = $block;
                        $preferredRow = null;
                        $displayBlock = $block;
                        $displayRow = null;
                    } elseif ($isRowOnlyPreference) {
                        $strategy = 'row_preferred';
                        $preferredBlock = 'center';
                        $preferredRow = $rowName;
                        $displayBlock = null;
                        $displayRow = $rowName;
                    } else {
                        $strategy = 'none';
                        $preferredBlock = 'center';
                        $preferredRow = null;
                        $displayBlock = null;
                        $displayRow = null;
                    }

                    $autoGroups[$groupKey] = [
                        'guest_name' => $guestName,
                        'comment' => $comment,
                        'user_display_name' => $userDisplayName,
                        'quantity' => 0,
                        'strategy' => $strategy,
                        'preferred_block' => $preferredBlock,
                        'preferred_row' => $preferredRow,
                        'display_block' => $displayBlock,
                        'display_row' => $displayRow,
                        // Earliest timestamp across the group's rows; ties broken by the
                        // first row's original CSV index.
                        'submission_timestamp' => $timestamp,
                        'first_row_index' => $rowIndex,
                    ];
                } else {
                    $existing = $autoGroups[$groupKey]['submission_timestamp'];
                    if ($timestamp !== null && ($existing === null || $timestamp < $existing)) {
                        $autoGroups[$groupKey]['submission_timestamp'] = $timestamp;
                    }
                }
                $autoGroups[$groupKey]['quantity'] += $requestedQuantity;
            }
        }

        // Oldest submission-timestamp first, tie-broken by original CSV row index. Groups
        // without a timestamp go last, in CSV order. uasort keeps the map keys.
        uasort($autoGroups, function ($a, $b) {
            $aHas = $a['submission_timestamp'] !== null;
            $bHas = $b['submission_timestamp'] !== null;

            if ($aHas !== $bHas) {
                return $aHas ? -1 : 1;
            }

            if ($aHas && $a['submission_timestamp'] !== $b['submission_timestamp']) {
                return strcmp($a['submission_timestamp'], $b['submission_timestamp']);
            }

            return $a['first_row_index'] <=> $b['first_row_index'];
        });

        foreach ($autoGroups as $group) {
            // Only "none" (no preference) is allowed to spill into a room-wide scan.
            $hasPreference = $group['strategy'] !== 'none';

            $assigned = $this->assignGroup($room, $group['quantity'], $group['strategy'], $group['preferred_block'], $group['preferred_row'], $unavailable, allowRoomFallback: ! $hasPreference);

            if ($assigned === null) {
                // A room genuinely out of free seats for this guest aborts the whole import,
                // regardless of preference - no point leaving them "unresolved" if there's
                // nowhere in the room they could ever be manually placed either.
                $freeCount = count(array_diff($allSeatIdsInOrder, $unavailable));

                if ($freeCount < $group['quantity']) {
                    return [
                        'error' => "Not enough available seats for {$group['guest_name']} (needs {$group['quantity']}, room has {$freeCount} seat(s) currently free).",
                        'guests' => [],
                    ];
                }

                if ($hasPreference) {
                    $area = $group['display_row'] !== null
                        ? ($group['display_block'] !== null
                            ? "Row '{$group['display_row']}' in block '{$group['display_block']}'"
                            : "Row '{$group['display_row']}'")
                        : "Block '{$group['display_block']}'";

                    $reason = $this->seats->blockExists($room, $group['preferred_block'])
                        ? "{$area} doesn't have {$group['quantity']} contiguous free seat(s) available."
                        : "{$area} was not found in this room.";

                    $guests[] = [
                        'guest_name' => $group['guest_name'],
                        'comment' => $group['comment'],
                        'user_display_name' => $group['user_display_name'],
                        'seat_ids' => [],
                        'requested_seats' => $group['quantity'],
                        'assignment_strategy' => $group['strategy'],
                        'preferred_block_name' => $group['display_block'],
                        'preferred_row_name' => $group['display_row'],
                        'submission_timestamp' => $group['submission_timestamp'],
                        'unresolved' => true,
                        'unresolved_reason' => "{$reason} Auto-placement wasn't possible - place these seats manually, or use \"Auto-place seats\" on the review screen to seat them elsewhere in the room.",
                    ];

                    continue;
                }

                $guests[] = [
                    'guest_name' => $group['guest_name'],
                    'comment' => $group['comment'],
                    'user_display_name' => $group['user_display_name'],
                    'seat_ids' => [],
                    'requested_seats' => $group['quantity'],
                    'assignment_strategy' => $group['strategy'],
                    'preferred_block_name' => $group['display_block'],
                    'preferred_row_name' => $group['display_row'],
                    'submission_timestamp' => $group['submission_timestamp'],
                    'unresolved' => true,
                    'unresolved_reason' => "Couldn't seat this guest together in one place ({$freeCount} free seat(s) remain in the room, but not as a single contiguous group). Use \"Auto-place seats\" on the review screen once seats free up, or pick seats manually.",
                ];

                continue;
            }

            $run = $assigned['seat_ids'];
            $unavailable = array_merge($unavailable, $run);
            $guests[] = [
                'guest_name' => $group['guest_name'],
                'comment' => $group['comment'],
                'user_display_name' => $group['user_display_name'],
                'seat_ids' => $run,
                'requested_seats' => $group['quantity'],
                'assignment_strategy' => $group['strategy'],
                'preferred_block_name' => $group['display_block'],
                'preferred_row_name' => $group['display_row'],
                'submission_timestamp' => $group['submission_timestamp'],
                'fallback_level_used' => $assigned['fallback_level'],
            ];
        }

        return ['error' => null, 'guests' => $guests];
    }

    /**
     * Assign one auto-assign group a contiguous run of $quantity free seats:
     *   - row_preferred: preferred row -> same block
     *   - block_preferred: preferred block
     *   - none: row-by-row, depth-first across every block ("center" first)
     * Ordering is stage-aware (see SeatAssigner). Also used by the review screen's
     * "Auto-place seats" button. $allowRoomFallback (default true) gates a final whole-room
     * scan once the strategy's own area is exhausted - build() passes false for any explicit
     * preference so it's left unresolved instead of seated elsewhere; the manual button
     * keeps it true, since that's an explicit admin action.
     * Returns ['seat_ids' => int[], 'fallback_level' => 'row'|'block'|'front_row'|'room'],
     * or null if the room can't fit the group (or fallback was disallowed).
     */
    public function assignGroup(Event|Room $eventOrRoom, int $quantity, string $strategy, ?string $preferredBlock, ?string $preferredRow, array $unavailable, bool $allowRoomFallback = true): ?array
    {
        $room = $eventOrRoom instanceof Event ? $eventOrRoom->room : $eventOrRoom;

        if ($strategy === 'none') {
            $preferredBlock = 'center';
            $preferredRow = null;
        }

        $run = null;
        $fallbackLevel = null;

        // Row-preferred: try the exact row first, then the rest of the block.
        if ($strategy === 'row_preferred') {
            $rowSeatIds = $this->seats->orderedRowSeatIds($room, $preferredBlock, $preferredRow, $unavailable);
            if (! empty($rowSeatIds)) {
                $run = $this->seats->findContiguousRun($rowSeatIds, $unavailable, $quantity);
                if ($run !== null) {
                    $fallbackLevel = 'row';
                }
            }

            if ($run === null) {
                $blockSeatIdsByRow = $this->seats->orderedBlockSeatIdsByRow($room, $preferredBlock, $unavailable, $preferredRow);
                if (! empty($blockSeatIdsByRow)) {
                    $run = $this->seats->findContiguousRoomRun($blockSeatIdsByRow, $unavailable, $quantity);
                }

                if ($run === null) {
                    $blockSeatIds = $this->seats->orderedBlockSeatIds($room, $preferredBlock, $unavailable, $preferredRow);
                    if (! empty($blockSeatIds)) {
                        $run = $this->seats->findContiguousRun($blockSeatIds, $unavailable, $quantity);
                    }
                }

                if ($run !== null) {
                    $fallbackLevel = 'block';
                }
            }
        } elseif ($strategy === 'block_preferred' && $preferredBlock !== null && $preferredBlock !== '') {
            // Try one whole row first, only split across rows if none fits.
            $blockSeatIdsByRow = $this->seats->orderedBlockSeatIdsByRow($room, $preferredBlock, $unavailable);
            if (! empty($blockSeatIdsByRow)) {
                $run = $this->seats->findContiguousRoomRun($blockSeatIdsByRow, $unavailable, $quantity);
            }

            if ($run === null) {
                $blockSeatIds = $this->seats->orderedBlockSeatIds($room, $preferredBlock, $unavailable);
                if (! empty($blockSeatIds)) {
                    $run = $this->seats->findContiguousRun($blockSeatIds, $unavailable, $quantity);
                }
            }

            if ($run !== null) {
                $fallbackLevel = 'block';
            }
        }

        // "none": one whole row at a time, depth-first across every block ("center" first),
        // before ever splitting a group across rows within a single block.
        if ($run === null && $strategy === 'none') {
            $seatIdsByRowDepth = $this->seats->orderedRoomSeatIdsByRowDepth($room, $unavailable, $preferredBlock);
            $run = $this->seats->findContiguousRoomRun($seatIdsByRowDepth, $unavailable, $quantity);
            if ($run !== null) {
                $fallbackLevel = 'front_row';
            }
        }

        // Whole-room scan, gated by $allowRoomFallback (see docblock).
        if ($run === null && $allowRoomFallback) {
            $seatIdsByBlock = $this->seats->orderedRoomSeatIdsByBlock($room, $unavailable);
            $run = $this->seats->findContiguousRoomRun($seatIdsByBlock, $unavailable, $quantity);
            if ($run !== null) {
                $fallbackLevel = 'room';
            }
        }

        if ($run === null) {
            return null;
        }

        return ['seat_ids' => $run, 'fallback_level' => $fallbackLevel];
    }

    /**
     * Hard-fail checks that don't need an Event/room (blank Guest Name, non-numeric Number
     * of Seats). build() runs this first; a global import runs it upfront for every queued
     * event's rows, so a bad row in event 3 fails the whole upload immediately.
     */
    public function validateRows(array $rows): ?string
    {
        foreach ($rows as $row) {
            $rowNumber = $row['_row'];

            if (mb_substr($row['guest name'], 0, 255) === '') {
                return "Row {$rowNumber}: Guest Name is required.";
            }

            if ($this->filledSeatFieldsCount($row) === 3) {
                continue; // exact-seat row, no Number of Seats column in play
            }

            $quantityRaw = $this->quantityRaw($row);

            // ctype_digit('0') passes, so also reject an explicit zero: reserve the blank
            // default of one for empty input only and require a positive whole number.
            if ($quantityRaw !== '' && (! ctype_digit((string) $quantityRaw) || (int) $quantityRaw < 1)) {
                return "Row {$rowNumber}: Number of Seats must be a positive whole number, got '{$quantityRaw}'.";
            }
        }

        return null;
    }

    /** How many of the Block/Row/Seat columns this row has filled in (0-3). */
    private function filledSeatFieldsCount(array $row): int
    {
        return ($row['block'] !== '' ? 1 : 0) + ($row['row'] !== '' ? 1 : 0) + ($row['seat'] !== '' ? 1 : 0);
    }

    /** Raw Number of Seats/Quantity/Seats column value for a row, whichever is present ('' if none). */
    private function quantityRaw(array $row): string
    {
        return $row['number of seats'] ?? $row['quantity'] ?? $row['seats'] ?? '';
    }

    /** Parsed quantity for an auto-assign row; blank defaults to 1. */
    private function quantity(array $row): int
    {
        $raw = $this->quantityRaw($row);

        return $raw !== '' ? (int) $raw : 1;
    }
}
