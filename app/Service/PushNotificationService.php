<?php

namespace App\Service;

use App\Models\User;
use App\Notifications\ManagerPushNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class PushNotificationService
{
    /**
     * Send a push notification to all users who have push subscriptions.
     */
    public static function broadcast(string $title, string $body, ?string $url = null, ?string $tag = null): void
    {
        $users = User::whereHas('pushSubscriptions')->get();

        if ($users->isEmpty()) {
            return;
        }

        try {
            Notification::send($users, new ManagerPushNotification($title, $body, $url, $tag));
        } catch (\Throwable $e) {
            Log::error('Push notification failed', [
                'title' => $title,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function newBooking(string $guestName, string $suite, string $dates): void
    {
        static::broadcast(
            "🏨 New Booking",
            "{$guestName} — {$suite}\n{$dates}",
            '/backoffice/concierge/booking',
            'new-booking',
        );
    }

    public static function upsellAccepted(string $guestName, string $offerTitle, string $price): void
    {
        static::broadcast(
            "💰 Upsell Accepted",
            "{$guestName} accepted {$offerTitle} ({$price})",
            '/backoffice/concierge/upsell-log',
            'upsell-accepted',
        );
    }

    public static function cancellation(string $guestName, string $suite): void
    {
        static::broadcast(
            "Booking Cancelled",
            "{$guestName} — {$suite} has been cancelled",
            '/backoffice/concierge/booking',
            'cancellation',
        );
    }

    public static function escalation(string $guestName, string $suite, string $reason): void
    {
        static::broadcast(
            "Escalation",
            "{$guestName} ({$suite}): {$reason}",
            '/backoffice/concierge/booking',
            'escalation',
        );
    }

    public static function serviceRequest(string $guestName, string $suite, string $request): void
    {
        static::broadcast(
            "Service Request",
            "{$guestName} ({$suite}): {$request}",
            '/backoffice/concierge/booking',
            'service-request',
        );
    }

    public static function customRequest(string $guestName, string $suite, string $request): void
    {
        static::broadcast(
            "Custom Request",
            "{$guestName} ({$suite}): {$request}",
            '/backoffice/concierge/booking',
            'custom-request',
        );
    }

    public static function dailyBriefing(int $arrivals, int $departures): void
    {
        static::broadcast(
            "Daily Briefing",
            "Today: {$arrivals} arrivals, {$departures} departures",
            '/backoffice',
            'daily-briefing',
        );
    }

    public static function healthAlert(string $summary): void
    {
        static::broadcast(
            "System Alert",
            $summary,
            '/backoffice/concierge/system-log',
            'health-alert',
        );
    }
}
