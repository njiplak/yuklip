<?php

namespace App\Http\Controllers\Api\Concierge;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SystemLog;
use App\Utils\WebResponse;

class AlertController extends Controller
{
    public function index()
    {
        $since = now()->subDays(7);

        $cancellations = Booking::where('booking_status', 'cancelled')
            ->where('updated_at', '>=', $since)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Booking $b) => [
                'id' => 'cancel-' . $b->id,
                'type' => 'cancellation',
                'title' => "{$b->suite_name} — {$b->guest_name}",
                'details' => implode(' · ', array_filter([
                    $b->num_nights . ' nights cancelled',
                    $b->check_in->format('M j') . '-' . $b->check_out->format('j'),
                    $b->booking_source,
                ])),
                'amount' => -(float) $b->total_amount,
                'currency' => $b->currency ?? 'EUR',
                'booking_id' => $b->id,
                'created_at' => $b->updated_at->toIso8601String(),
            ]);

        $escalations = SystemLog::where(function ($q) {
                $q->where('action', 'escalated_to_manager')
                    ->orWhere('action', 'sentiment_escalated');
            })
            ->where('created_at', '>=', $since)
            ->with('booking')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SystemLog $log) => [
                'id' => 'escalation-' . $log->id,
                'type' => 'escalation',
                'title' => $log->booking
                    ? "{$log->booking->guest_name} — {$log->booking->suite_name}"
                    : 'Manager Escalation',
                'details' => $this->escalationDetails($log),
                'booking_id' => $log->booking_id,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        $alerts = $cancellations->merge($escalations)
            ->sortByDesc('created_at')
            ->values();

        return WebResponse::json($alerts, 'Alerts retrieved.');
    }

    protected function escalationDetails(SystemLog $log): string
    {
        if (isset($log->payload['reason'])) {
            return $log->payload['reason'];
        }

        if (isset($log->payload['sentiment'])) {
            return match ($log->payload['sentiment']) {
                'issue_detected' => 'Yasmine escalated — guest appears frustrated or upset. Automated messaging paused.',
                'handover_human' => 'Yasmine escalated — could not interpret guest message. Requires human attention.',
                default => 'Yasmine escalated — requires manager attention.',
            };
        }

        return match ($log->action) {
            'escalated_to_manager' => 'Yasmine escalated — guest not responding after follow-ups. Requires human attention.',
            default => 'Yasmine escalated — requires manager attention.',
        };
    }
}
