<?php

namespace App\Booking;

use App\Models\Event;

class EventMatcher
{
    /**
     * Group parsed CSV rows by their `event` column and resolve each group's name to a real
     * Event, matching "softly" (case-insensitive, whitespace-collapsed, punctuation/symbols
     * ignored - see normalize()) rather than requiring a byte-for-byte match.
     * Returns an error string naming the first unmatched value, or the first pair of events
     * that become indistinguishable once normalized (the whole file is rejected rather than
     * silently dropping rows or silently picking one of several equally-matching events).
     *
     * @return array<int, array{event: Event, rows: array}>|string
     */
    public function groupRowsByEvent(array $rows): array|string
    {
        $eventsByName = [];
        foreach (Event::all() as $event) {
            $key = $this->normalize($event->name);

            if (isset($eventsByName[$key]) && $eventsByName[$key]->id !== $event->id) {
                return "Events '{$eventsByName[$key]->name}' and '{$event->name}' are too similar to tell apart for import (ignoring case/whitespace/symbols) - rename one and re-upload.";
            }

            $eventsByName[$key] = $event;
        }

        $groups = [];
        $order = [];

        foreach ($rows as $row) {
            $eventName = $row['event'];
            $key = $this->normalize($eventName);

            if (! isset($groups[$key])) {
                if ($eventName === '' || ! isset($eventsByName[$key])) {
                    return "Row {$row['_row']}: no event found named '{$eventName}'. Fix the CSV and re-upload.";
                }

                $groups[$key] = ['event' => $eventsByName[$key], 'rows' => []];
                $order[] = $key;
            }

            $groups[$key]['rows'][] = $row;
        }

        return array_map(fn ($key) => $groups[$key], $order);
    }

    /**
     * Same "soft" matching as groupRowsByEvent() (case/whitespace/symbols ignored), for a
     * single-event import that wants to honor an optional `Event` column against the one
     * event it's already importing into, without the multi-event grouping/ambiguity checks.
     */
    public function matches(string $rowEventName, string $eventName): bool
    {
        return $this->normalize($rowEventName) === $this->normalize($eventName);
    }

    /**
     * Normalize an event name for "soft" matching in global import: lowercased, punctuation/
     * symbols stripped (replaced with a space so words never get glued together), and
     * whitespace collapsed - e.g. "Opening Ceremony: Hall 3" and "opening ceremony hall 3"
     * normalize identically.
     */
    private function normalize(string $name): string
    {
        // mb_strtolower so accented / non-Latin characters fold correctly - strtolower()
        // would leave "Événement" and "événement" as different keys.
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name);

        return trim($name);
    }
}
