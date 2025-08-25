<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\TelegramNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckReservationPeriods extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:check-reservation-periods';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for ended reservation periods and send Telegram notifications';

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
        $this->info('Checking for ended reservation periods...');

        // Get events with reservation periods that have recently ended (within the last 10 minutes)
        // This ensures we catch events even if the command runs slightly delayed
        $endedEvents = Event::whereNotNull('reservation_ends_at')
            ->where('reservation_ends_at', '<=', Carbon::now())
            ->where('reservation_ends_at', '>=', Carbon::now()->subMinutes(10))
            ->get();

        $notificationsSent = 0;

        foreach ($endedEvents as $event) {
            // Check if we've already sent a "reservation ended" notification for this event
            $notifications = $event->notifications_sent ?? [];
            if (in_array('reservation_period_ended', $notifications)) {
                continue; // Skip if already notified
            }

            // Send Telegram notification
            $success = $this->telegramService->notifyReservationPeriodEnded(
                $event->name ?? "Event #{$event->id}",
                $event->reservation_ends_at
            );

            if ($success) {
                // Mark notification as sent
                $notifications[] = 'reservation_period_ended';
                $event->update(['notifications_sent' => $notifications]);
                $notificationsSent++;

                $this->info("Sent reservation ended notification for event: {$event->name}");
            } else {
                $this->error("Failed to send notification for event: {$event->name}");
            }
        }

        $this->info("Checked {$endedEvents->count()} events with ended reservations. Sent {$notificationsSent} notifications.");

        return 0;
    }
}
