<?php

namespace Tests\Unit;

use App\Services\TelegramNotificationService;
use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class TelegramNotificationServiceTest extends TestCase
{
    public function test_telegram_service_handles_missing_config_gracefully()
    {
        // Clear the config to test error handling
        Config::set('services.telegram.bot_token', '');
        Config::set('services.telegram.chat_id', '');

        $service = new TelegramNotificationService();
        $result = $service->sendMessage('Test message');
        
        $this->assertFalse($result);
    }

    public function test_telegram_service_sends_room_fully_booked_notification()
    {
        // Mock the HTTP client
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        Config::set('services.telegram.bot_token', 'test_token');
        Config::set('services.telegram.chat_id', 'test_chat_id');

        $service = new TelegramNotificationService();
        $result = $service->notifyRoomFullyBooked('Test Event', 'Test Room', 50);
        
        $this->assertTrue($result);
        
        // Assert that the HTTP request was made
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/bottest_token/sendMessage'
                && $request['chat_id'] === 'test_chat_id'
                && str_contains($request['text'], 'Room Fully Booked Alert')
                && str_contains($request['text'], 'Test Event')
                && str_contains($request['text'], 'Test Room');
        });
    }

    public function test_telegram_service_sends_reservation_ended_notification()
    {
        // Mock the HTTP client
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        Config::set('services.telegram.bot_token', 'test_token');
        Config::set('services.telegram.chat_id', 'test_chat_id');

        $service = new TelegramNotificationService();
        $endTime = new \DateTime('2023-12-25 15:30:00');
        $result = $service->notifyReservationPeriodEnded('Test Event', $endTime);
        
        $this->assertTrue($result);
        
        // Assert that the HTTP request was made
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/bottest_token/sendMessage'
                && $request['chat_id'] === 'test_chat_id'
                && str_contains($request['text'], 'Reservation Period Ended')
                && str_contains($request['text'], 'Test Event')
                && str_contains($request['text'], '2023-12-25 15:30:00');
        });
    }
}