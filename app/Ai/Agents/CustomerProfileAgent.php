<?php

namespace App\Ai\Agents;

use App\Models\Customer;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

class CustomerProfileAgent implements Agent
{
    use Promptable;

    public function __construct(protected Customer $customer) {}

    public function provider(): Lab
    {
        return Lab::Anthropic;
    }

    public function instructions(): string
    {
        $raw = json_encode($this->customer->raw_preferences ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $stays = $this->customer->total_stays;
        $lastStay = $this->customer->last_stay_at?->format('M Y') ?? 'unknown';

        return implode("\n", [
            'You are summarizing a returning guest\'s profile for a riad concierge AI.',
            'The summary will be injected into a WhatsApp concierge system prompt to personalize conversation.',
            '',
            '## Guest',
            "- Name: {$this->customer->name}",
            "- Total stays: {$stays}",
            "- Last stay: {$lastStay}",
            $this->customer->nationality ? "- Nationality: {$this->customer->nationality}" : null,
            $this->customer->language ? "- Language: {$this->customer->language}" : null,
            '',
            '## Raw Preferences (accumulated across all stays)',
            $raw,
            '',
            '## Your Task',
            'Write a concise profile summary (3-5 lines max) that a concierge AI can use to personalize service.',
            'Include: stay count, persistent preferences (bed type, transfer, dietary, language), likes/dislikes, and any staff notes.',
            'Omit anything that changes per-stay (arrival time, specific dates).',
            'Use plain text, no markdown. No preamble — output ONLY the summary.',
        ]);
    }
}
