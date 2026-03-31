import type { Model } from './model';
import type { Offer } from './offer';

export type Booking = Model & {
    lodgify_booking_id: string;
    guest_name: string;
    guest_phone: string;
    guest_email: string | null;
    guest_nationality: string | null;
    num_guests: number;
    suite_name: string;
    check_in: string;
    check_out: string;
    num_nights: number;
    booking_source: string;
    booking_status: string;
    total_amount: string;
    currency: string;
    special_requests: string | null;
    internal_notes: string | null;
    conversation_state: string;
    pref_arrival_time: string | null;
    pref_bed_type: string | null;
    pref_airport_transfer: string | null;
    pref_special_requests: string | null;
    current_upsell_offer_id: number | null;
    upsell_offer_sent_at: string | null;
    current_offer?: Offer;
    upsell_logs?: UpsellLog[];
    whatsapp_messages?: WhatsappMessage[];
};

export type UpsellLog = Model & {
    booking_id: number;
    offer_id: number;
    message_sent: string;
    sent_at: string;
    guest_reply: string | null;
    reply_received_at: string | null;
    outcome: string | null;
    revenue_generated: string | null;
    booking?: Booking;
    offer?: Offer;
};

export type WhatsappMessage = Model & {
    booking_id: number | null;
    direction: string;
    phone_number: string;
    message_body: string;
    agent_source: string | null;
    twochat_message_id: string | null;
    sent_at: string | null;
    received_at: string | null;
};
