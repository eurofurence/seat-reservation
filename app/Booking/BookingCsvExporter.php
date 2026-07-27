<?php

namespace App\Booking;

use App\Models\Booking;
use App\Models\Event;

class BookingCsvExporter
{
    private const HEADER = "Room,Event,Name,Guest Name,Comment,Block,Row,Seat,Picked Up,Booked At\n";

    /**
     * Build a bookings CSV. Pass $event when every booking belongs to the same event (the
     * single-event export); leave it null to resolve each booking's own event (export all).
     */
    public function csv(iterable $bookings, ?Event $event = null): string
    {
        $csv = self::HEADER;

        foreach ($bookings as $booking) {
            $csv .= $this->row($event ?? $booking->event, $booking);
        }

        return $csv;
    }

    private function row(Event $event, Booking $booking): string
    {
        return sprintf(
            "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
            $this->escape($event->room->name ?? 'N/A'),
            $this->escape($event->name),
            $this->escape($booking->user ? $booking->user->name : 'N/A'),
            $this->escape($booking->name ?? 'N/A'),
            $this->escape($booking->comment ?? ''),
            $this->escape($booking->seat->row->block->name ?? 'N/A'),
            $this->escape($booking->seat->row->name ?? 'N/A'),
            $this->escape($booking->seat->label ?? 'N/A'),
            $booking->picked_up_at ? 'Yes' : 'No',
            $booking->created_at->format('Y-m-d H:i:s')
        );
    }

    private function escape($field): string
    {
        // Escape quotes and wrap in quotes if contains comma, quote, or newline
        $field = str_replace('"', '""', $field);
        if (strpos($field, ',') !== false || strpos($field, '"') !== false || strpos($field, "\n") !== false) {
            $field = '"'.$field.'"';
        }

        return $field;
    }
}
