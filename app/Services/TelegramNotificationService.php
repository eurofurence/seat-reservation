<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    protected string $botToken;

    protected string $chatId;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->chatId = config('services.telegram.chat_id');
    }

    public function sendMessage(string $message): bool
    {
        if (empty($this->botToken) || empty($this->chatId)) {
            Log::warning('Telegram bot token or chat ID not configured');

            return false;
        }

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                'chat_id' => $this->chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);

            if ($response->successful()) {
                Log::info('Telegram notification sent successfully', ['message' => $message]);

                return true;
            } else {
                Log::error('Failed to send Telegram notification', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'message' => $message,
                ]);

                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception while sending Telegram notification', [
                'error' => $e->getMessage(),
                'message' => $message,
            ]);

            return false;
        }
    }

    public function notifyRoomFullyBooked(string $eventName, string $roomName, int $totalSeats): bool
    {
        $message = "🔴 <b>Room Fully Booked Alert</b>\n\n";
        $message .= "Event: <b>{$eventName}</b>\n";
        $message .= "Room: <b>{$roomName}</b>\n";
        $message .= "Total Seats: <b>{$totalSeats}</b>\n\n";
        $message .= 'The room has reached maximum capacity for priority access tickets.';

        return $this->sendMessage($message);
    }

    public function notifyReservationPeriodEnded(string $eventName, \DateTime $reservationEndTime): bool
    {
        $message = "⏰ <b>Reservation Period Ended</b>\n\n";
        $message .= "Event: <b>{$eventName}</b>\n";
        $message .= "Reservation ended: <b>{$reservationEndTime->format('Y-m-d H:i:s')}</b>\n\n";
        $message .= 'The reservation period for this event has ended.';

        return $this->sendMessage($message);
    }
}
