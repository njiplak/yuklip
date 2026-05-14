<?php

namespace App\Ai\Agents;

use App\Models\Booking;
use App\Models\MenuItem;
use App\Models\Offer;
use App\Models\Setting;
use App\Models\WhatsappMessage;
use App\Service\Booking\StayContext;
use Carbon\Carbon;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

class GuestReplyAgent implements Agent, Conversational
{
    use Promptable;

    /** Hours before a previously-asked preference can be asked again. */
    public const ASK_COOLDOWN_HOURS = 24;

    /**
     * Preference fields in the order they should be asked. Logistical urgency
     * (arrival, transfer) comes before comfort (bed) and open-ended (requests).
     *
     * @var array<string, string>
     */
    protected const PREFERENCE_PRIORITY = [
        'arrival_time' => 'arrival time',
        'airport_transfer' => 'whether they need an airport transfer',
        'bed_type' => 'bed preference (double bed or twin beds)',
        'special_requests' => 'any special requests (allergies, celebrations, dietary needs, baby cot, etc.)',
    ];

    /**
     * Human-readable labels for the "already collected" summary, separate from
     * the priority labels which are phrased as questions.
     */
    protected const PREFERENCE_DISPLAY_LABELS = [
        'arrival_time' => 'Arrival time',
        'airport_transfer' => 'Airport transfer',
        'bed_type' => 'Bed type',
        'special_requests' => 'Special requests',
    ];

    private ?string $cachedNextPreference = null;
    private bool $nextPreferenceComputed = false;

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

        $stayContext = StayContext::format(StayContext::compute($this->booking));
        $returningGuestContext = $this->returningGuestContext();
        $preferenceInstructions = $this->preferenceInstructions();
        $serviceRequestGuidelines = $this->serviceRequestGuidelines();
        $menuContext = $this->menuContext();
        $offerContext = $this->offerContext();

        return $systemPrompt . "\n\n" . $guestContext
            . "\n\n" . $stayContext
            . ($returningGuestContext ? "\n\n" . $returningGuestContext : '')
            . ($preferenceInstructions ? "\n\n" . $preferenceInstructions : '')
            . "\n\n" . $serviceRequestGuidelines
            . ($menuContext ? "\n\n" . $menuContext : '')
            . ($offerContext ? "\n\n" . $offerContext : '');
    }

    /**
     * Which preference should the bot ask about this turn, if any.
     * Deterministic so the controller can persist the matching timestamp
     * after dispatch — the bot is directed to ask about exactly this field.
     *
     * Returns null when all preferences are collected, when none are
     * eligible (all recently asked within cooldown), or when the booking
     * is in the post-collection state.
     */
    public function nextPreferenceToAsk(): ?string
    {
        if (!$this->nextPreferenceComputed) {
            $this->cachedNextPreference = $this->computeNextPreferenceToAsk();
            $this->nextPreferenceComputed = true;
        }

        return $this->cachedNextPreference;
    }

    protected function computeNextPreferenceToAsk(): ?string
    {
        if (($this->booking->conversation_state ?? 'preferences_complete') === 'preferences_complete') {
            return null;
        }

        $asked = $this->booking->preferences_asked ?? [];
        $cooldownThreshold = Carbon::now()->subHours(self::ASK_COOLDOWN_HOURS);

        foreach (self::PREFERENCE_PRIORITY as $key => $_label) {
            if ($this->booking->{"pref_{$key}"}) {
                continue; // already collected
            }

            $askedAt = $asked[$key] ?? null;

            if ($askedAt && Carbon::parse($askedAt)->isAfter($cooldownThreshold)) {
                continue; // asked too recently — wait
            }

            return $key;
        }

        return null;
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

        $asked = $this->booking->preferences_asked ?? [];
        $cooldownThreshold = Carbon::now()->subHours(self::ASK_COOLDOWN_HOURS);

        $collected = [];
        $waiting = [];   // missing, but asked recently — DO NOT re-ask this turn

        foreach (self::PREFERENCE_PRIORITY as $key => $label) {
            $value = $this->booking->{"pref_{$key}"};

            if ($value) {
                $displayLabel = self::PREFERENCE_DISPLAY_LABELS[$key];
                $collected[] = "{$displayLabel}: {$value}";
                continue;
            }

            $askedAt = $asked[$key] ?? null;
            if ($askedAt && Carbon::parse($askedAt)->isAfter($cooldownThreshold)) {
                $waiting[] = $label;
            }
        }

        $nextToAsk = $this->nextPreferenceToAsk();
        $nextLabel = $nextToAsk ? self::PREFERENCE_PRIORITY[$nextToAsk] : null;

        $collectedText = empty($collected) ? 'Nothing yet.' : implode(', ', $collected);

        $lines = [
            '## Preference Collection (ACTIVE)',
            '',
            'You are collecting stay preferences from this guest. Be conversational, not interrogative.',
            '',
            "Already collected: {$collectedText}",
        ];

        if ($nextLabel) {
            $lines[] = '';
            $lines[] = "**Ask about exactly ONE preference this turn: {$nextLabel}**";
        }

        if (!empty($waiting)) {
            $lines[] = '';
            $lines[] = 'DO NOT re-ask these — they were asked recently and the guest has not answered yet. Wait for them to bring it up.';
            foreach ($waiting as $item) {
                $lines[] = "- {$item}";
            }
        }

        if (!$nextLabel && empty($waiting)) {
            // No missing prefs left and state hasn't flipped yet — fall back to summary.
            return $this->collectedPreferencesSummary();
        }

        $lines[] = '';
        $lines[] = 'Guidelines:';
        $lines[] = '- If the guest provides preferences in their message, acknowledge them warmly.';

        if ($nextLabel) {
            $lines[] = '- After acknowledging, ask about EXACTLY the one preference marked above. Never ask for multiple at once.';
        } else {
            $lines[] = '- Do NOT ask any preference questions this turn — all pending ones were already asked recently.';
        }

        $lines[] = '- If the guest asks a question, answer it first.';
        $lines[] = '- If the guest declines or says "no special requests" or "that\'s all", accept it.';
        $lines[] = '- Match the guest\'s language and energy.';

        return implode("\n", $lines);
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

    protected function offerContext(): ?string
    {
        $offers = Offer::active()
            ->orderBy('timing_rule')
            ->orderBy('title')
            ->get();

        if ($offers->isEmpty()) {
            return null;
        }

        $grouped = $offers->groupBy('timing_rule')->map(function ($bucket, $rule) {
            $lines = $bucket->map(function (Offer $offer) {
                $priceSegment = $offer->price
                    ? " — {$offer->price} {$offer->currency}"
                    : '';

                return "- [{$offer->offer_code}] {$offer->title}{$priceSegment}: {$offer->description}";
            })->implode("\n");

            $header = ucfirst(str_replace('_', ' ', $rule));

            return "### {$header}\n{$lines}";
        })->implode("\n\n");

        return implode("\n", [
            '## Available Add-on Offers',
            '',
            'These are bookable experiences, services, and upgrades the guest can purchase. Each offer is grouped by its `timing_rule` — the stay moment when it is most relevant. Cross-reference with the guest\'s current stay phase and day_of_stay from "Current Status" above.',
            '',
            'When to mention an offer:',
            '- Proactively: only when the current stay phase or day_of_stay matches the offer\'s timing_rule bucket.',
            '- Reactively: if the guest asks about activities, experiences, dining add-ons, or transfers, you may mention any relevant offer regardless of timing.',
            '- Never fabricate offers that are not listed here. If a guest asks for something not on this list, tell them you will check with the team.',
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
