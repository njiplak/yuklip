<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\SystemLog;
use Carbon\Carbon;
use Inertia\Inertia;

class BackofficeController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        $arrivals = Booking::where('check_in', $today)
            ->whereIn('booking_status', ['confirmed', 'checked_in'])
            ->get(['id', 'guest_name', 'suite_name', 'num_guests', 'guest_nationality', 'guest_phone', 'pref_arrival_time', 'pref_airport_transfer', 'conversation_state']);

        $departures = Booking::where('check_out', $today)
            ->whereIn('booking_status', ['checked_in', 'checked_out'])
            ->get(['id', 'guest_name', 'suite_name']);

        $checkedIn = Booking::where('booking_status', 'checked_in')->count();

        $pendingPreferences = Booking::whereIn('conversation_state', ['waiting_preferences', 'preferences_partial'])
            ->whereIn('booking_status', ['confirmed', 'checked_in'])
            ->count();

        $handoverCount = Booking::where('conversation_state', 'handover_human')
            ->whereIn('booking_status', ['confirmed', 'checked_in'])
            ->count();

        $recentErrors = SystemLog::where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return Inertia::render('backoffice', [
            'stats' => [
                'arrivals' => $arrivals->count(),
                'departures' => $departures->count(),
                'checked_in' => $checkedIn,
                'pending_preferences' => $pendingPreferences,
                'handover_count' => $handoverCount,
                'recent_errors' => $recentErrors,
            ],
            'arrivals' => $arrivals,
            'departures' => $departures,
        ]);
    }
}
