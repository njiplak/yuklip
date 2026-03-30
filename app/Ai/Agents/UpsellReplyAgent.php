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
        return implode("\n", [
            'You are classifying a guest reply to an upsell offer at Riad Larbi Khalis.',
            '',
            '## Offer That Was Sent',
            "- Title: {$this->offer->title}",
            "- Description: {$this->offer->description}",
            "- Price: {$this->offer->price} {$this->offer->currency}",
            '',
            '## Guest',
            "- Name: {$this->booking->guest_name}",
            '',
            'Classify the guest reply as one of: accepted, declined, unclear.',
            'Also write a short, warm WhatsApp reply in the same language the guest used.',
            'If accepted, confirm enthusiastically. If declined, be gracious. If unclear, ask a clarifying question.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'classification' => $schema->string()->enum(['accepted', 'declined', 'unclear'])->required(),
            'reply_message' => $schema->string()->required(),
        ];
    }
}
