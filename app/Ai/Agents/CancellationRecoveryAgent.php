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
        $daysUntilCheckIn = now()->diffInDays($this->booking->check_in, false);

        return implode("\n", [
            'You are generating a cancellation recovery action plan for the manager and owner of Riad Larbi Khalis, a boutique 4-suite riad in Marrakech.',
            '',
            '## Cancelled Booking',
            "- Guest: {$this->booking->guest_name}",
            '- Nationality: ' . ($this->booking->guest_nationality ?? 'Unknown'),
            "- Suite: {$this->booking->suite_name}",
            "- Dates: {$this->booking->check_in->format('d M')} - {$this->booking->check_out->format('d M, Y')} ({$this->booking->num_nights} nights)",
            "- Value: {$this->booking->total_amount} {$this->booking->currency}",
            "- Source: {$this->booking->booking_source}",
            "- Days until original check-in: {$daysUntilCheckIn}",
            '',
            '## Your Task',
            'Generate a recovery action plan with concrete, actionable recommendations:',
            '',
            '1. **Pricing adjustments**: Should the nightly rate for this suite/period be reduced temporarily? By how much? Consider the time until check-in.',
            '2. **Minimum stay adjustment**: Should minimum night requirements be reduced for this period to attract last-minute bookings?',
            '3. **Platform tactics**: Should the suite be listed on additional platforms? Should instant booking be enabled? Any special promotions?',
            '4. **Direct outreach**: Is it worth reaching out to the guest with a flexible offer (alternative dates, discount)?',
            '5. **Urgency assessment**: How critical is filling this gap based on proximity to check-in date and season?',
            '',
            'Format the plan clearly for WhatsApp. Use numbered sections. Be specific with numbers and percentages.',
            'Keep it under 1500 characters. Actionable, not theoretical.',
            '',
            'Output ONLY the recovery plan. No preamble.',
        ]);
    }
}
