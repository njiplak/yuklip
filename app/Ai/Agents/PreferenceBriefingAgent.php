<?php

namespace App\Ai\Agents;

use App\Models\Booking;
use Carbon\Carbon;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

class PreferenceBriefingAgent implements Agent
{
    use Promptable;

    public function __construct(protected Booking $booking) {}

    public function provider(): Lab
    {
        return Lab::Anthropic;
    }

    public function instructions(): string
    {
        $b = $this->booking;

        $checkIn = $b->check_in instanceof Carbon ? $b->check_in->format('d M Y') : (string) $b->check_in;
        $checkOut = $b->check_out instanceof Carbon ? $b->check_out->format('d M Y') : (string) $b->check_out;

        $facts = [
            'guest_name' => $b->guest_name,
            'suite' => $b->suite_name,
            'num_guests' => $b->num_guests,
            'nationality' => $b->guest_nationality ?: 'unknown',
            'detected_language' => $b->detected_language ?: 'unknown',
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'num_nights' => $b->num_nights,
            'arrival_time' => $b->pref_arrival_time ?: 'not provided',
            'bed_type' => $b->pref_bed_type ?: 'not provided',
            'airport_transfer' => $b->pref_airport_transfer ?: 'not provided',
            'special_requests' => $b->pref_special_requests ?: 'not provided',
        ];

        $factsList = collect($facts)
            ->map(fn ($v, $k) => "- {$k}: {$v}")
            ->implode("\n");

        return implode("\n", [
            'You are generating an end-of-preference-collection conclusion briefing for staff at Riad Larbi Khalis.',
            'All preferences below were collected from the guest via WhatsApp and are now finalized.',
            '',
            '## Facts (use ALL of these — do not omit any)',
            $factsList,
            '',
            '## Output format — strict',
            'Produce a bullet-style WhatsApp message in TWO languages, French first then Arabic (Darija or Standard Arabic).',
            'Each language section MUST follow this exact skeleton:',
            '',
            '🇫🇷 *Préférences confirmées — {guest_name}*',
            '• Suite: {suite} ({num_guests} pers.)',
            '• Arrivée: {check_in} → Départ: {check_out} ({num_nights} nuits)',
            '• Heure d\'arrivée: {arrival_time}',
            '• Lit: {bed_type}',
            '• Transfert aéroport: {airport_transfer}',
            '• Demandes spéciales: {special_requests}',
            '• Nationalité / langue: {nationality} / {detected_language}',
            '',
            '🇲🇦 *تأكيد التفضيلات — {guest_name}*',
            '• [same bullets, translated]',
            '',
            '## Rules',
            '- Render every bullet for every field, even when the value is "not provided" — translate that phrase appropriately ("non communiqué" / "غير محدد").',
            '- Do NOT invent, summarize away, or drop any field. Zero context loss.',
            '- Keep each bullet to one short line. Be clear and direct, not flowery.',
            '- Use the bullet character "•". Do not use markdown headers, dashes, or numbered lists.',
            '- Output ONLY the bilingual briefing message. No preamble, no closing remarks.',
        ]);
    }
}
