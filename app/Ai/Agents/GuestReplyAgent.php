<?php

namespace App\Ai\Agents;

use App\Models\Booking;
use App\Models\Setting;
use App\Models\WhatsappMessage;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

class GuestReplyAgent implements Agent, Conversational
{
    use Promptable;

    public function __construct(protected Booking $booking) {}

    public function provider(): Lab
    {
        return Lab::Anthropic;
    }

    public function instructions(): string
    {
        $systemPrompt = Setting::where('key', 'concierge_system_prompt')->value('value')
            ?? 'You are a helpful AI concierge. Be concise and friendly.';

        $guestContext = implode("\n", [
            "## Current Guest Context",
            "- Guest: {$this->booking->guest_name}",
            "- Suite: {$this->booking->suite_name}",
            "- Check-in: {$this->booking->check_in->format('Y-m-d')}",
            "- Check-out: {$this->booking->check_out->format('Y-m-d')}",
            "- Nights: {$this->booking->num_nights}",
            "- Guests: {$this->booking->num_guests}",
            $this->booking->guest_nationality ? "- Nationality: {$this->booking->guest_nationality}" : '',
            $this->booking->special_requests ? "- Special Requests: {$this->booking->special_requests}" : '',
        ]);

        return $systemPrompt . "\n\n" . $guestContext;
    }

    /**
     * Load conversation history for this booking.
     *
     * The current inbound message is excluded because the caller passes it
     * via prompt() — including it here would duplicate it in the AI context.
     */
    public function messages(): iterable
    {
        $messages = WhatsappMessage::where('booking_id', $this->booking->id)
            ->orderBy('created_at', 'desc')
            ->limit(11)
            ->get()
            ->reverse()
            ->values();

        if ($messages->isNotEmpty() && $messages->last()->direction === 'inbound') {
            $messages->pop();
        }

        return $messages->take(10)->map(function (WhatsappMessage $msg) {
            $role = $msg->direction === 'inbound' ? 'user' : 'assistant';
            return new Message($role, $msg->message_body);
        })->all();
    }
}
