<?php

namespace App\Http\Controllers\Api\Concierge;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SystemLog;
use App\Models\UpsellLog;
use App\Utils\WebResponse;

class ActivityController extends Controller
{
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;
    private const WINDOW_DAYS = 7;

    public function index()
    {
        $limit = min(
            self::MAX_LIMIT,
            max(1, (int) request('limit', self::DEFAULT_LIMIT))
        );

        $since = now()->subDays(self::WINDOW_DAYS);

        $items = collect()
            ->merge($this->newBookings($since))
            ->merge($this->upsellOutcomes($since))
            ->merge($this->cancellations($since))
            ->merge($this->escalations($since));

        $sorted = $items
            ->sortByDesc('created_at')
            ->values()
            ->take($limit);

        return WebResponse::json($sorted, 'Activity retrieved.');
    }

    private function newBookings($since)
    {
        return Booking::with('customer')
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(self::MAX_LIMIT)
            ->get()
            ->map(fn (Booking $b) => [
                'id' => 'booking-' . $b->id,
                'type' => 'new_booking',
                'title' => 'New booking',
                'subtitle' => $b->suite_name,
                'actor' => $b->guest_name,
                'extra' => trim(implode(' · ', array_filter([
                    $b->num_nights ? $b->num_nights . ' nights' : null,
                    $b->booking_source,
                ]))) ?: null,
                'amount' => $b->total_amount !== null ? (float) $b->total_amount : null,
                'currency' => $b->currency ?? 'EUR',
                'booking_id' => $b->id,
                'created_at' => $b->created_at->toIso8601String(),
            ]);
    }

    private function upsellOutcomes($since)
    {
        return UpsellLog::with(['offer', 'booking'])
            ->whereIn('outcome', ['accepted', 'declined'])
            ->where('reply_received_at', '>=', $since)
            ->orderByDesc('reply_received_at')
            ->limit(self::MAX_LIMIT)
            ->get()
            ->map(fn (UpsellLog $log) => [
                'id' => 'upsell-' . $log->id,
                'type' => $log->outcome === 'accepted' ? 'upsell_accepted' : 'upsell_declined',
                'title' => $log->outcome === 'accepted' ? 'Upsell accepted' : 'Upsell declined',
                'subtitle' => $log->offer?->title,
                'actor' => $log->booking?->guest_name,
                'extra' => $log->booking?->suite_name,
                'amount' => $log->outcome === 'accepted' && $log->revenue_generated !== null
                    ? (float) $log->revenue_generated
                    : null,
                'currency' => $log->booking?->currency ?? 'EUR',
                'booking_id' => $log->booking_id,
                'created_at' => ($log->reply_received_at ?? $log->sent_at ?? $log->created_at)->toIso8601String(),
            ]);
    }

    private function cancellations($since)
    {
        return Booking::where('booking_status', 'cancelled')
            ->where('updated_at', '>=', $since)
            ->orderByDesc('updated_at')
            ->limit(self::MAX_LIMIT)
            ->get()
            ->map(fn (Booking $b) => [
                'id' => 'cancel-' . $b->id,
                'type' => 'cancellation',
                'title' => 'Booking cancelled',
                'subtitle' => $b->suite_name,
                'actor' => $b->guest_name,
                'extra' => $b->booking_source,
                'amount' => $b->total_amount !== null ? -(float) $b->total_amount : null,
                'currency' => $b->currency ?? 'EUR',
                'booking_id' => $b->id,
                'created_at' => $b->updated_at->toIso8601String(),
            ]);
    }

    private function escalations($since)
    {
        return SystemLog::with('booking')
            ->whereIn('action', ['escalated_to_manager', 'sentiment_escalated'])
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(self::MAX_LIMIT)
            ->get()
            ->map(fn (SystemLog $log) => [
                'id' => 'escalation-' . $log->id,
                'type' => 'escalation',
                'title' => 'Escalation',
                'subtitle' => $log->booking?->suite_name,
                'actor' => $log->booking?->guest_name,
                'extra' => $this->escalationDetail($log),
                'amount' => null,
                'currency' => null,
                'booking_id' => $log->booking_id,
                'created_at' => $log->created_at->toIso8601String(),
            ]);
    }

    private function escalationDetail(SystemLog $log): ?string
    {
        if (isset($log->payload['reason'])) {
            return $log->payload['reason'];
        }
        if (isset($log->payload['sentiment'])) {
            return $log->payload['sentiment'];
        }
        return $log->action;
    }
}
