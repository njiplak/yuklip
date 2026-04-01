<?php

namespace App\Ai\Agents;

use App\Models\Booking;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

class ServiceRequestDetectorAgent implements Agent, HasStructuredOutput
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
            'You are analyzing a WhatsApp message from a hotel guest to determine if it contains an actionable service request that requires physical staff action.',
            '',
            '## Guest Context',
            "- Guest: {$this->booking->guest_name}",
            "- Suite: {$this->booking->suite_name}",
            '',
            '## What IS a service request (requires_staff_action = true)',
            '- Food or drink orders ("I want melon juice", "can we get dinner tonight?")',
            '- Room issues ("AC is not working", "need extra towels", "the shower is leaking")',
            '- Transport arrangements ("can you book a taxi for 3pm?")',
            '- Activity or excursion bookings ("I want to book the hammam", "arrange a day trip")',
            '- Any request that requires staff to physically do something or procure something',
            '',
            '## What is NOT a service request (requires_staff_action = false)',
            '- General questions ("what is the wifi password?", "what time is checkout?")',
            '- Providing preference answers ("I prefer twin beds", "around 3pm")',
            '- Casual conversation, greetings, thank-yous, compliments',
            '- Asking for recommendations or information ("what restaurants are nearby?")',
            '- Confirming or acknowledging something the bot said',
            '',
            '## Urgency',
            '- urgent: health/safety issues, broken essential amenities (no water, no AC in extreme heat, security concerns)',
            '- normal: everything else',
            '',
            '## Rules',
            '- The guest may write in any language. Detect the request regardless of language.',
            '- Always write the request_summary in English (for staff).',
            '- Be conservative: only flag clear, actionable requests. When in doubt, return false.',
            '- A single message may contain both a question and a request. If it contains a request, flag it.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'requires_staff_action' => $schema->boolean(),
            'request_summary' => $schema->string()->nullable(),
            'urgency' => $schema->string()->enum(['normal', 'urgent']),
        ];
    }
}
