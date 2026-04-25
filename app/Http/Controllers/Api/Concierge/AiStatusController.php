<?php

namespace App\Http\Controllers\Api\Concierge;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SystemLog;
use App\Models\WhatsappMessage;
use App\Utils\WebResponse;

class AiStatusController extends Controller
{
    /** Maps SystemLog action -> mobile-facing activity kind. */
    private const ACTIVITY_KIND_MAP = [
        'offer_sent' => 'upsell_offer',
        'welcome_sent' => 'welcome',
        'reply_sent' => 'reply',
        'briefing_sent' => 'briefing',
        'recovery_plan_sent' => 'recovery',
        'recovery_sent' => 'recovery',
    ];

    /** Window within which a SystemLog row counts as "current activity". */
    private const CURRENT_ACTIVITY_WINDOW_MINUTES = 30;

    public function index()
    {
        $stored = Setting::where('key', 'ai_enabled')->value('value');
        // Default to enabled when no setting row exists; otherwise parse the stored
        // string ("0"/"false"/"no"/"off" all map to false; missing -> true).
        $active = $stored === null
            ? true
            : (bool) filter_var($stored, FILTER_VALIDATE_BOOLEAN);

        $messageCount24h = WhatsappMessage::where('created_at', '>=', now()->subDay())->count();

        return WebResponse::json([
            'active' => $active,
            'message_count_24h' => $messageCount24h,
            'current_activity' => $this->currentActivity(),
        ], 'AI status retrieved.');
    }

    private function currentActivity(): ?array
    {
        $log = SystemLog::whereIn('action', array_keys(self::ACTIVITY_KIND_MAP))
            ->where('status', 'success')
            ->where('created_at', '>=', now()->subMinutes(self::CURRENT_ACTIVITY_WINDOW_MINUTES))
            ->with(['booking.currentOffer'])
            ->latest('created_at')
            ->first();

        if (!$log) {
            return null;
        }

        $booking = $log->booking;

        return [
            'kind' => self::ACTIVITY_KIND_MAP[$log->action],
            'offer_title' => $booking?->currentOffer?->title,
            'guest_name' => $booking?->guest_name,
            'booking_id' => $log->booking_id,
            'since' => $log->created_at->toIso8601String(),
        ];
    }
}
