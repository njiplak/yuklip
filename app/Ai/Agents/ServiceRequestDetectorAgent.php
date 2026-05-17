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
            'You analyze a WhatsApp message from a hotel guest and classify the manager-notification intent.',
            '',
            '## Guest Context',
            "- Guest: {$this->booking->guest_name}",
            "- Suite: {$this->booking->suite_name}",
            '',
            '## Intent values',
            '',
            '### urgent',
            'Safety, health, or broken essential amenities that need immediate human attention.',
            'Examples: "no water in the shower", "the AC is broken and it is very hot", "I feel unwell",',
            '"the door lock is not working", "there is a fire / gas smell", "I am locked out".',
            '',
            '### request',
            'Any actionable physical service request — staff must do or procure something.',
            'Examples: food/drink orders ("can we get melon juice", "dinner tonight?"),',
            'room issues that are not emergencies ("extra towels please", "the lamp is flickering"),',
            'transport ("book a taxi for 3pm", "pick-up from the airport"),',
            'activities/excursions ("book the hammam", "arrange a day trip"),',
            'any clear ask for something to be done.',
            '',
            '### info',
            'The guest is asking a question or seeking information / recommendations / clarification —',
            'no staff action required, but the manager should still be aware in case they want to chime in.',
            'Examples: "what is the wifi password?", "what time is checkout?", "is the pool open today?",',
            '"any good restaurants nearby?", "do you have a hairdryer?", "where can I exchange money?".',
            '',
            '### none',
            'Casual conversation, greetings, thanks, acknowledgements, emojis, or direct preference answers',
            'that were prompted by the bot during preference collection.',
            'Examples: "thanks!", "ok", "👍", "around 3pm" (answering arrival time), "twin beds please",',
            '"sounds good", "merci", "see you soon".',
            '',
            '## Routing rules',
            '- A single message may mix intents. Pick the highest-priority intent present:',
            '  urgent > request > info > none.',
            '- When the message is ambiguous between info and none, lean towards info — managers prefer to',
            '  see borderline questions than to miss them.',
            '- When the message is ambiguous between request and info, lean towards request.',
            '- Do NOT classify preference answers as info, even if phrased as a question back to the bot,',
            '  unless they contain a separate ask.',
            '',
            '## Output rules',
            '- summary: a short English staff-facing summary of what the guest wants. Required for',
            '  urgent / request / info. Use null for none.',
            '- The guest may write in any language. Always write the summary in English.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'intent' => $schema->string()->enum(['urgent', 'request', 'info', 'none']),
            'summary' => $schema->string()->nullable(),
        ];
    }
}
