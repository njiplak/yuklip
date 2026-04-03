<?php

namespace App\Ai\Agents;

use App\Models\Booking;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

class PreferenceExtractorAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(protected Booking $booking) {}

    public function provider(): Lab
    {
        return Lab::Anthropic;
    }

    public function instructions(): string
    {
        $collected = [];
        $missing = [];

        if ($this->booking->pref_arrival_time) {
            $collected[] = "- Arrival time: {$this->booking->pref_arrival_time}";
        } else {
            $missing[] = 'arrival_time';
        }

        if ($this->booking->pref_bed_type) {
            $collected[] = "- Bed type: {$this->booking->pref_bed_type}";
        } else {
            $missing[] = 'bed_type';
        }

        if ($this->booking->pref_airport_transfer) {
            $collected[] = "- Airport transfer: {$this->booking->pref_airport_transfer}";
        } else {
            $missing[] = 'airport_transfer';
        }

        if ($this->booking->pref_special_requests) {
            $collected[] = "- Special requests: {$this->booking->pref_special_requests}";
        } else {
            $missing[] = 'special_requests';
        }

        $collectedText = empty($collected) ? 'None yet.' : implode("\n", $collected);
        $missingText = implode(', ', $missing);

        return implode("\n", [
            'You are extracting guest preferences from a WhatsApp conversation reply.',
            'The guest is replying to the concierge who asked about their stay preferences.',
            '',
            '## Guest',
            "- Name: {$this->booking->guest_name}",
            "- Check-in: {$this->booking->check_in->format('Y-m-d')}",
            '',
            '## Already Collected',
            $collectedText,
            '',
            '## Still Missing',
            $missingText ?: 'All preferences collected.',
            '',
            '## Your Task',
            'Extract any of these 4 preferences from the guest message:',
            '',
            '1. **arrival_time**: What time they plan to arrive (e.g. "3pm", "around 15:00", "afternoon", "late evening"). Accept any reasonable time expression.',
            '2. **bed_type**: Bed preference (e.g. "double", "twin", "two single beds", "king", "queen"). Normalize to "double" or "twin".',
            '3. **airport_transfer**: Whether they need airport pickup (e.g. "yes please", "no thanks", "we have a car"). Normalize to "yes" or "no".',
            '4. **special_requests**: Any special needs — allergies, celebrations, dietary requirements, baby cot, accessibility needs, etc. Keep the guest\'s own words.',
            '',
            'Rules:',
            '- Only extract what is clearly stated. Do not guess or infer.',
            '- If the message says "no special requests" or "nothing special", set special_requests to "none".',
            '- A message may contain zero, one, or all four preferences.',
            '- Return null for any preference not mentioned in this message.',
            '- Do not overwrite already-collected preferences — only return values for missing ones.',
            '- The guest may reply in any language. Extract the preference regardless of language.',
            '',
            '## Sentiment Detection',
            '- If the guest is angry, frustrated, or complaining, set sentiment to "issue_detected".',
            '- If the message is gibberish, a single emoji, or completely uninterpretable, set sentiment to "handover_human".',
            '- Otherwise, set sentiment to "normal".',
            '- Also detect the language the guest is writing in and return it as detected_language (ISO 639-1 code: en, fr, ar, es, de, etc.).',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'arrival_time' => $schema->string()->nullable(),
            'bed_type' => $schema->string()->enum(['double', 'twin'])->nullable(),
            'airport_transfer' => $schema->string()->enum(['yes', 'no'])->nullable(),
            'special_requests' => $schema->string()->nullable(),
            'sentiment' => $schema->string()->enum(['normal', 'issue_detected', 'handover_human']),
            'detected_language' => $schema->string()->nullable(),
        ];
    }
}
