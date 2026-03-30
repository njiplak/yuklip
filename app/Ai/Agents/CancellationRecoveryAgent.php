<?php

namespace App\Ai\Agents;

use App\Models\Booking;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

class CancellationRecoveryAgent implements Agent
{
    use Promptable;

    public function __construct(protected Booking $booking) {}

    public function provider(): Lab
    {
        return Lab::Anthropic;
    }

    public function instructions(): string
    {
        return implode("\n", [
            'You are a warm concierge at Riad Larbi Khalis, a boutique riad in Marrakech.',
            'A guest has just cancelled their booking. Write a short, non-pushy WhatsApp message.',
            'Express understanding, mention you\'d love to welcome them another time, and gently offer flexibility (alternative dates or a small discount on a future stay).',
            'Write in the guest\'s likely language based on their nationality. If unsure, use English and French.',
            'Keep it to 2-3 sentences. Warm, human, not corporate.',
            '',
            '## Guest',
            "- Name: {$this->booking->guest_name}",
            "- Nationality: " . ($this->booking->guest_nationality ?? 'Unknown'),
            "- Original dates: {$this->booking->check_in->format('M d')} - {$this->booking->check_out->format('M d, Y')}",
            "- Suite: {$this->booking->suite_name}",
            '',
            'Output ONLY the WhatsApp message text. No preamble.',
        ]);
    }
}
