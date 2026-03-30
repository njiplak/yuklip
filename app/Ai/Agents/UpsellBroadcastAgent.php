<?php

namespace App\Ai\Agents;

use App\Models\Booking;
use App\Models\Offer;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

class UpsellBroadcastAgent implements Agent
{
    use Promptable;

    public function __construct(
        protected Booking $booking,
        protected Offer $offer,
    ) {}

    public function provider(): Lab
    {
        return Lab::Anthropic;
    }

    public function instructions(): string
    {
        return implode("\n", [
            'You are a warm, professional concierge at Riad Larbi Khalis, a boutique riad in Marrakech.',
            'Write a personalized WhatsApp message offering this experience to the guest.',
            'Keep it concise (2-3 sentences max), warm, and non-pushy.',
            'Write in the guest\'s likely language based on their nationality. If unsure, use English.',
            'Include the price naturally.',
            '',
            '## Guest',
            "- Name: {$this->booking->guest_name}",
            "- Nationality: " . ($this->booking->guest_nationality ?? 'Unknown'),
            "- Suite: {$this->booking->suite_name}",
            "- Day of stay: " . ($this->booking->currentDayOfStay() ?? '?'),
            '',
            '## Offer',
            "- Title: {$this->offer->title}",
            "- Description: {$this->offer->description}",
            "- Price: {$this->offer->price} {$this->offer->currency}",
            '',
            'Output ONLY the WhatsApp message text. No preamble, no explanation.',
        ]);
    }
}
