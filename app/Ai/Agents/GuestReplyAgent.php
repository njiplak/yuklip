<?php

namespace App\Ai\Agents;

use App\Models\Booking;
use App\Models\MenuItem;
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

        $guestContext = implode("\n", array_filter([
            "## Current Guest Context",
            "- Guest: {$this->booking->guest_name}",
            "- Suite: {$this->booking->suite_name}",
            "- Check-in: {$this->booking->check_in->format('Y-m-d')}",
            "- Check-out: {$this->booking->check_out->format('Y-m-d')}",
            "- Nights: {$this->booking->num_nights}",
            "- Guests: {$this->booking->num_guests}",
            $this->booking->guest_nationality ? "- Nationality: {$this->booking->guest_nationality}" : null,
            $this->booking->special_requests ? "- Special Requests: {$this->booking->special_requests}" : null,
        ]));

        $returningGuestContext = $this->returningGuestContext();
        $preferenceInstructions = $this->preferenceInstructions();
        $serviceRequestGuidelines = $this->serviceRequestGuidelines();
        $menuContext = $this->menuContext();

        return $systemPrompt . "\n\n" . $guestContext
            . ($returningGuestContext ? "\n\n" . $returningGuestContext : '')
            . ($preferenceInstructions ? "\n\n" . $preferenceInstructions : '')
            . "\n\n" . $serviceRequestGuidelines
            . ($menuContext ? "\n\n" . $menuContext : '');
    }

    protected function returningGuestContext(): ?string
    {
        $customer = $this->booking->customer;

        if (!$customer || !$customer->isReturning() || !$customer->profile_summary) {
            return null;
        }

        return implode("\n", [
            '## Returning Guest Profile',
            '',
            "This guest has stayed {$customer->total_stays} time(s) before. Acknowledge their return warmly.",
            '',
            $customer->profile_summary,
        ]);
    }

    protected function preferenceInstructions(): ?string
    {
        $state = $this->booking->conversation_state ?? 'preferences_complete';

        if ($state === 'preferences_complete') {
            return $this->collectedPreferencesSummary();
        }

        $collected = [];
        $missing = [];

        if ($this->booking->pref_arrival_time) {
            $collected[] = "Arrival time: {$this->booking->pref_arrival_time}";
        } else {
            $missing[] = 'arrival time';
        }

        if ($this->booking->pref_bed_type) {
            $collected[] = "Bed type: {$this->booking->pref_bed_type}";
        } else {
            $missing[] = 'bed preference (double bed or twin beds)';
        }

        if ($this->booking->pref_airport_transfer) {
            $collected[] = "Airport transfer: {$this->booking->pref_airport_transfer}";
        } else {
            $missing[] = 'whether they need airport transfer';
        }

        if ($this->booking->pref_special_requests) {
            $collected[] = "Special requests: {$this->booking->pref_special_requests}";
        } else {
            $missing[] = 'any special requests (allergies, celebrations, dietary needs, etc.)';
        }

        $collectedText = empty($collected) ? 'Nothing yet.' : implode(', ', $collected);
        $missingText = implode(', ', $missing);

        return implode("\n", [
            '## Preference Collection (ACTIVE)',
            '',
            'You are currently collecting stay preferences from this guest. This is your priority alongside answering any questions they have.',
            '',
            "Already collected: {$collectedText}",
            "Still needed: {$missingText}",
            '',
            'Guidelines:',
            '- If the guest provides preferences in their message, acknowledge them warmly.',
            '- After acknowledging, naturally ask about the NEXT missing preference. Do not ask for multiple at once.',
            '- If the guest asks a question (e.g. "what time is check-in?"), answer it first, then gently steer back to the missing preferences.',
            '- If the guest seems reluctant or says "no special requests" or "that\'s all", that is fine — accept it.',
            '- Never be pushy. The guest should feel like a natural conversation, not an interrogation.',
            '- Match the guest\'s language and energy.',
        ]);
    }

    protected function serviceRequestGuidelines(): string
    {
        return implode("\n", [
            '## Handling Service Requests',
            '',
            'When a guest makes a specific request (food/drink order, room issue, transport, activity booking):',
            '- Acknowledge the request warmly.',
            '- Tell the guest you are passing it to the team right away and they will take care of it.',
            '- Do NOT say "let me check" or imply you will personally verify — the team handles it.',
            '- Do NOT fabricate availability information you do not have.',
        ]);
    }

    protected function menuContext(): ?string
    {
        $items = MenuItem::available()
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        if ($items->isEmpty()) {
            return null;
        }

        $grouped = $items->groupBy('category')->map(function ($categoryItems, $category) {
            $lines = $categoryItems->map(function (MenuItem $item) {
                $price = $item->price ? " ({$item->price} {$item->currency})" : ' (included)';
                $note = $item->availability_note ? " — {$item->availability_note}" : '';

                return "- {$item->name}{$price}{$note}";
            })->implode("\n");

            return '### ' . ucfirst($category) . "\n" . $lines;
        })->implode("\n\n");

        return implode("\n", [
            '## Available Menu & Drinks',
            '',
            'When a guest asks about food or drinks, use ONLY this list. If an item is not listed here, tell the guest you will check with the kitchen.',
            '',
            $grouped,
        ]);
    }

    protected function collectedPreferencesSummary(): ?string
    {
        $items = array_filter([
            $this->booking->pref_arrival_time
                ? "- Arrival: {$this->booking->pref_arrival_time} on {$this->booking->check_in->format('D, M d, Y')}"
                : null,
            $this->booking->pref_bed_type
                ? "- Bed type: {$this->booking->pref_bed_type}"
                : null,
            $this->booking->pref_airport_transfer
                ? "- Airport transfer: {$this->booking->pref_airport_transfer}"
                : null,
            ($this->booking->pref_special_requests && $this->booking->pref_special_requests !== 'none')
                ? "- Special requests: {$this->booking->pref_special_requests}"
                : null,
        ]);

        if (empty($items)) {
            return null;
        }

        return implode("\n", [
            '## Guest Preferences (COLLECTED — DO NOT RE-ASK)',
            '',
            'All stay preferences have been collected and confirmed. Do NOT ask about any of these again.',
            '',
            ...$items,
            '',
            'Use this information to provide informed, personalized answers.',
            'If the guest discusses scheduling activities, transfers, or excursions, cross-reference with their arrival time and check-in/check-out dates to catch any conflicts (e.g., activity scheduled before they arrive or after they depart).',
        ]);
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
