<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

class StaffBriefingAgent implements Agent
{
    use Promptable;

    public function __construct(
        protected array $arrivals,
        protected array $departures,
    ) {}

    public function provider(): Lab
    {
        return Lab::Anthropic;
    }

    public function instructions(): string
    {
        $arrivalsText = empty($this->arrivals)
            ? 'None'
            : collect($this->arrivals)->map(fn ($b) =>
                "- {$b['guest_name']} | {$b['suite_name']} | {$b['num_guests']} guests | {$b['guest_nationality']} | {$b['special_requests']}"
            )->implode("\n");

        $departuresText = empty($this->departures)
            ? 'None'
            : collect($this->departures)->map(fn ($b) =>
                "- {$b['guest_name']} | {$b['suite_name']}"
            )->implode("\n");

        return implode("\n", [
            'Generate a concise daily staff briefing for Riad Larbi Khalis.',
            'Format it clearly for WhatsApp (use emojis sparingly for readability).',
            '',
            'IMPORTANT: The briefing MUST be bilingual — write each section first in French, then in Arabic (Darija/Standard Arabic).',
            'Use this format:',
            '  🇫🇷 [French section]',
            '  🇲🇦 [Arabic section]',
            '',
            '## Arrivals Today',
            $arrivalsText,
            '',
            '## Departures Today',
            $departuresText,
            '',
            'Include all guest preferences, special requests, and transfer details.',
            'Output ONLY the briefing message. No preamble.',
        ]);
    }
}
