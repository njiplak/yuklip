<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\SystemLog;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckoutArchiveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('agents');
    }

    public function handle(): void
    {
        $today = Carbon::today();

        // Mark checked_in bookings as checked_out if checkout date has passed
        $bookings = Booking::where('check_out', '<=', $today)
            ->where('booking_status', 'checked_in')
            ->get();

        foreach ($bookings as $booking) {
            $booking->update(['booking_status' => 'checked_out']);

            SystemLog::create([
                'agent' => 'checkout_archive',
                'action' => 'checked_out',
                'booking_id' => $booking->id,
                'status' => 'success',
                'payload' => ['check_out' => $booking->check_out->toDateString()],
            ]);
        }

        // Also mark confirmed bookings whose checkout has passed (guest never checked in)
        $missedBookings = Booking::where('check_out', '<=', $today)
            ->where('booking_status', 'confirmed')
            ->get();

        foreach ($missedBookings as $booking) {
            $booking->update(['booking_status' => 'checked_out']);

            SystemLog::create([
                'agent' => 'checkout_archive',
                'action' => 'checked_out',
                'booking_id' => $booking->id,
                'status' => 'success',
                'payload' => [
                    'check_out' => $booking->check_out->toDateString(),
                    'note' => 'was_confirmed_never_checked_in',
                ],
            ]);
        }

        if ($bookings->isEmpty() && $missedBookings->isEmpty()) {
            SystemLog::create([
                'agent' => 'checkout_archive',
                'action' => 'skipped',
                'status' => 'skipped',
                'payload' => ['reason' => 'no_bookings_to_archive'],
            ]);
        }
    }
}
