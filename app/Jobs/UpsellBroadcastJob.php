<?php

namespace App\Jobs;

use App\Ai\Agents\UpsellBroadcastAgent;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\SystemLog;
use App\Models\UpsellLog;
use App\Models\WhatsappMessage;
use App\Service\WhatsApp\TwoChatService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpsellBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('agents');
    }

    public function handle(TwoChatService $twoChat): void
    {
        $bookings = Booking::where('booking_status', 'checked_in')->get();

        foreach ($bookings as $booking) {
            $this->processBooking($booking, $twoChat);
        }
    }

    protected function processBooking(Booking $booking, TwoChatService $twoChat): void
    {
        if (!$booking->guest_phone) {
            return;
        }

        $dayOfStay = $booking->currentDayOfStay();

        if (!$dayOfStay) {
            return;
        }

        $timingRule = $this->resolveTimingRule($booking, $dayOfStay);

        // Find matching active offers respecting max_sends_per_stay
        $sentCounts = UpsellLog::where('booking_id', $booking->id)
            ->selectRaw('offer_id, count(*) as send_count')
            ->groupBy('offer_id')
            ->pluck('send_count', 'offer_id');

        $offer = Offer::active()
            ->where('timing_rule', $timingRule)
            ->get()
            ->first(fn (Offer $o) => $sentCounts->get($o->id, 0) < $o->max_sends_per_stay);

        if (!$offer) {
            SystemLog::create([
                'agent' => 'upsell_cron',
                'action' => 'skipped',
                'booking_id' => $booking->id,
                'payload' => ['day_of_stay' => $dayOfStay, 'timing_rule' => $timingRule],
                'status' => 'skipped',
            ]);
            return;
        }

        $start = microtime(true);

        try {
            $response = (new UpsellBroadcastAgent($booking, $offer))->prompt('Generate the upsell message.');
            $message = (string) $response;

            $result = $twoChat->sendMessage($booking->guest_phone, $message);

            // Only update state after message is confirmed sent
            $booking->update([
                'current_upsell_offer_id' => $offer->id,
                'upsell_offer_sent_at' => now(),
            ]);

            UpsellLog::create([
                'booking_id' => $booking->id,
                'offer_id' => $offer->id,
                'message_sent' => $message,
                'sent_at' => now(),
                'outcome' => 'pending',
            ]);

            WhatsappMessage::create([
                'booking_id' => $booking->id,
                'direction' => 'outbound',
                'phone_number' => $booking->guest_phone,
                'message_body' => $message,
                'agent_source' => 'upsell_cron',
                'twochat_message_id' => $result['message_uuid'] ?? null,
                'sent_at' => now(),
            ]);

            SystemLog::create([
                'agent' => 'upsell_cron',
                'action' => 'offer_sent',
                'booking_id' => $booking->id,
                'payload' => ['offer_code' => $offer->offer_code, 'day_of_stay' => $dayOfStay],
                'status' => 'success',
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
        } catch (\Throwable $e) {
            Log::error('Upsell broadcast failed', ['booking_id' => $booking->id, 'offer' => $offer->offer_code, 'error' => $e->getMessage()]);

            SystemLog::create([
                'agent' => 'upsell_cron',
                'action' => 'offer_sent',
                'booking_id' => $booking->id,
                'payload' => ['offer_code' => $offer->offer_code, 'day_of_stay' => $dayOfStay],
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
        }
    }

    protected function resolveTimingRule(Booking $booking, int $dayOfStay): string
    {
        // diffInDays returns 0 on checkout day itself, 1 on the day before
        $daysUntilCheckout = (int) Carbon::today()->diffInDays($booking->check_out);

        if ($dayOfStay === 1) {
            return 'arrival_day';
        }

        // Day before checkout (diffInDays returns 1 when checkout is tomorrow)
        if ($daysUntilCheckout === 1) {
            return 'day_1_before_checkout';
        }

        return "day_{$dayOfStay}";
    }
}
