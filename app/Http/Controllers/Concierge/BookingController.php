<?php

namespace App\Http\Controllers\Concierge;

use App\Contract\Concierge\BookingContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\Concierge\BookingRequest;
use App\Models\Booking;
use App\Utils\WebResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;

class BookingController extends Controller
{
    protected BookingContract $service;

    public function __construct(BookingContract $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return Inertia::render('concierge/booking/index');
    }

    public function fetch()
    {
        $data = $this->service->all(
            allowedFilters: [],
            allowedSorts: [],
            withPaginate: true,
            perPage: request()->get('per_page', 10),
        );
        return response()->json($data);
    }

    public function create()
    {
        return Inertia::render('concierge/booking/form');
    }

    public function store(BookingRequest $request)
    {
        $data = $this->service->create($request->validated());
        return WebResponse::response($data, 'backoffice.concierge.booking.index');
    }

    public function show($id)
    {
        $data = $this->service->find($id, ['currentOffer', 'upsellLogs.offer', 'whatsappMessages']);
        return Inertia::render('concierge/booking/form', [
            'booking' => $data,
        ]);
    }

    public function update(BookingRequest $request, $id)
    {
        $data = $this->service->update($id, $request->validated());
        return WebResponse::response($data, 'backoffice.concierge.booking.index');
    }

    public function destroy($id)
    {
        $data = $this->service->destroy($id);
        return WebResponse::response($data, 'backoffice.concierge.booking.index');
    }

    public function destroy_bulk(Request $request)
    {
        $data = $this->service->bulkDeleteByIds($request->ids ?? []);
        return WebResponse::response($data, 'backoffice.concierge.booking.index');
    }

    public function exportConversation($id): Response
    {
        $booking = Booking::with(['whatsappMessages', 'customer'])->findOrFail($id);

        $messages = $booking->whatsappMessages
            ->sortBy('created_at')
            ->values();

        $lines = [];

        // Header
        $lines[] = "# Conversation — {$booking->guest_name}";
        $lines[] = '';

        // Context block
        $lines[] = '## Context';
        $lines[] = "- **Guest**: {$booking->guest_name}";
        $lines[] = "- **Phone**: {$booking->guest_phone}";
        $lines[] = "- **Suite**: {$booking->suite_name}";
        $lines[] = "- **Check-in**: {$booking->check_in->format('Y-m-d')} | **Check-out**: {$booking->check_out->format('Y-m-d')} | **Nights**: {$booking->num_nights}";
        $lines[] = "- **Guests**: {$booking->num_guests}";

        if ($booking->guest_nationality) {
            $lines[] = "- **Nationality**: {$booking->guest_nationality}";
        }

        $lines[] = "- **Source**: {$booking->booking_source}";
        $lines[] = "- **Status**: {$booking->booking_status}";
        $lines[] = "- **Conversation State**: {$booking->conversation_state}";

        $customer = $booking->customer;
        if ($customer && $customer->isReturning()) {
            $lines[] = "- **Returning Guest**: Yes ({$customer->total_stays} stays)";
        }

        // Preferences
        $prefs = array_filter([
            'Arrival Time' => $booking->pref_arrival_time,
            'Bed Type' => $booking->pref_bed_type,
            'Airport Transfer' => $booking->pref_airport_transfer,
            'Special Requests' => $booking->pref_special_requests,
        ]);

        if (!empty($prefs)) {
            $lines[] = '';
            $lines[] = '## Preferences Collected';
            foreach ($prefs as $label => $value) {
                $lines[] = "- **{$label}**: {$value}";
            }
        }

        // Messages
        $lines[] = '';
        $lines[] = '## Conversation';
        $lines[] = '';

        if ($messages->isEmpty()) {
            $lines[] = '*No messages.*';
        }

        foreach ($messages as $msg) {
            $timestamp = $msg->sent_at ?? $msg->received_at ?? $msg->created_at;
            $time = $timestamp ? date('Y-m-d H:i', strtotime($timestamp)) : '';

            if ($msg->direction === 'inbound') {
                $lines[] = "**[GUEST]** ({$time})";
            } else {
                $source = $msg->agent_source ? " — {$msg->agent_source}" : '';
                $lines[] = "**[BOT{$source}]** ({$time})";
            }

            $lines[] = $msg->message_body;
            $lines[] = '';
        }

        $markdown = implode("\n", $lines);

        $filename = str_replace(' ', '_', strtolower($booking->guest_name))
            . '_' . $booking->check_in->format('Y-m-d')
            . '_conversation.md';

        return response($markdown, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
