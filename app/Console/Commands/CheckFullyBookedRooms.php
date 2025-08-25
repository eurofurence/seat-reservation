<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\TelegramNotificationService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CheckFullyBookedRooms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:check-fully-booked';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for fully booked rooms and send Telegram notifications';

    protected TelegramNotificationService $telegramService;

    public function __construct(TelegramNotificationService $telegramService)
    {
        parent::__construct();
        $this->telegramService = $telegramService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for fully booked rooms...');

        // Get active events (current or future events)
        $activeEvents = Event::with(['room.blocks.rows.seats', 'bookings'])
            ->where(function ($query) {
                $query->where('starts_at', '>=', Carbon::now())
                      ->orWhere('reservation_ends_at', '>=', Carbon::now());
            })
            ->get();

        $notificationsSent = 0;

        foreach ($activeEvents as $event) {
            // Check if we've already sent a "fully booked" notification for this event
            $notifications = $event->notifications_sent ?? [];
            if (in_array('room_fully_booked', $notifications)) {
                continue; // Skip if already notified
            }

            // Calculate if the room is fully booked
            $maxTickets = $event->max_tickets ?? $event->tickets ?? 0;
            if ($maxTickets === 0) {
                // If no ticket limit is set, use seat availability
                $totalSeats = $event->room->blocks()
                    ->with('rows.seats')
                    ->get()
                    ->flatMap(fn($block) => $block->rows->flatMap(fn($row) => $row->seats))
                    ->count();
                $maxTickets = $totalSeats;
            }

            $bookedTickets = $event->bookings()->count();
            $isFullyBooked = $bookedTickets >= $maxTickets;

            if ($isFullyBooked && $maxTickets > 0) {
                // Send Telegram notification
                $success = $this->telegramService->notifyRoomFullyBooked(
                    $event->name ?? "Event #{$event->id}",
                    $event->room->name ?? "Room #{$event->room->id}",
                    $maxTickets
                );

                if ($success) {
                    // Mark notification as sent
                    $notifications[] = 'room_fully_booked';
                    $event->update(['notifications_sent' => $notifications]);
                    $notificationsSent++;

                    $this->info("Sent fully booked notification for event: {$event->name}");
                } else {
                    $this->error("Failed to send notification for event: {$event->name}");
                }
            }
        }

        $this->info("Checked {$activeEvents->count()} events. Sent {$notificationsSent} notifications.");
        
        return 0;
    }
}
