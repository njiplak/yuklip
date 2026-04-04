<?php

namespace App\Ai\Agents;

use App\Models\Booking;
use App\Models\Offer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

class UpsellReplyAgent implements Agent, HasStructuredOutput
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
        $offerCatalog = Offer::active()
            ->get(['id', 'offer_code', 'title', 'description', 'price', 'currency'])
            ->map(fn (Offer $o) => "- [{$o->offer_code}] {$o->title}: {$o->description} ({$o->price} {$o->currency})")
            ->implode("\n");

        return implode("\n", [
            'You are classifying a guest reply to an upsell offer at Riad Larbi Khalis, a boutique riad in Marrakech.',
            '',
            '## Offer That Was Sent',
            "- Title: {$this->offer->title}",
            "- Description: {$this->offer->description}",
            "- Price: {$this->offer->price} {$this->offer->currency}",
            "- Offer Code: {$this->offer->offer_code}",
            '',
            '## Guest',
            "- Name: {$this->booking->guest_name}",
            '- Nationality: ' . ($this->booking->guest_nationality ?? 'Unknown'),
            '',
            '## Full Offer Catalog (for matching alternative requests)',
            $offerCatalog ?: '(No other active offers)',
            '',
            '## Classification Rules',
            'Classify the guest reply into exactly one of these 6 categories:',
            '',
            '**accepted** — Guest wants the offered service.',
            '  Examples: "Yes please", "Breakfast for two at 9am", "Sure!", thumbs up.',
            '',
            '**declined** — Guest does not want the offered service.',
            '  Examples: "No thank you", "We already have plans", "Not interested".',
            '',
            '**question** — Guest asks about the offer (price, time, details).',
            '  Examples: "How much is it?", "What time?", "Is the hammam private?".',
            '',
            '**different_request** — Guest wants something ELSE, not the offered service.',
            '  Examples: "Can I get a massage instead?", "Do you offer cooking classes?".',
            '  Check the offer catalog above. If the request matches another offer, set alternative_offer_code.',
            '',
            '**accept_and_more** — Guest accepts the offer AND requests something additional.',
            '  Examples: "Yes breakfast, and can you also book a hammam?".',
            '  Treat as accepted for the original offer. The additional request is noted separately.',
            '',
            '**unrelated** — Guest message has nothing to do with the offer.',
            '  Examples: "What time is checkout?", "Where is the nearest pharmacy?".',
            '  Answer the question naturally. Do NOT re-present the offer.',
            '',
            '## Response Rules',
            '- Write reply_message as Yasmine — warm, concise WhatsApp message in the guest\'s language.',
            '- For accepted: confirm enthusiastically with any details they mentioned.',
            '- For declined: be gracious, no pressure.',
            '- For question: answer using offer details, then gently re-present the offer.',
            '- For different_request: acknowledge warmly, say "Let me check with our team."',
            '- For accept_and_more: confirm the accepted offer, then say "Let me check on that" for the extra.',
            '- For unrelated: answer their question naturally. Do NOT mention the offer again.',
            '- If guest mentions specific details (time, number of people), capture them in details field.',
            '- If guest requests something matching another offer in the catalog, set alternative_offer_code.',
            '- If guest requests something NOT in the catalog, describe it in custom_request.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'classification' => $schema->string()->enum([
                'accepted', 'declined', 'question',
                'different_request', 'accept_and_more', 'unrelated',
            ])->required(),
            'reply_message' => $schema->string()->required(),
            'details' => $schema->string()->nullable(),
            'alternative_offer_code' => $schema->string()->nullable(),
            'custom_request' => $schema->string()->nullable(),
        ];
    }
}
