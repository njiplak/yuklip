import { router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { index } from '@/routes/backoffice/concierge/booking';
import type { Booking, UpsellLog, WhatsappMessage } from '@/types/booking';

const statusColor: Record<string, string> = {
    confirmed: 'bg-blue-50 text-blue-700 ring-blue-700/10',
    checked_in: 'bg-green-50 text-green-700 ring-green-700/10',
    checked_out: 'bg-gray-50 text-gray-600 ring-gray-600/10',
    cancelled: 'bg-red-50 text-red-700 ring-red-700/10',
};

const upsellOutcomeColor: Record<string, string> = {
    accepted: 'bg-green-50 text-green-700 ring-green-700/10',
    declined: 'bg-red-50 text-red-700 ring-red-700/10',
    pending: 'bg-yellow-50 text-yellow-700 ring-yellow-700/10',
};

function Field({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex flex-col gap-0.5">
            <span className="text-xs font-medium text-muted-foreground">{label}</span>
            <span className="text-sm">{value || <span className="text-muted-foreground">-</span>}</span>
        </div>
    );
}

function Badge({ text, color }: { text: string; color: string }) {
    return (
        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${color}`}>
            {text.replace('_', ' ')}
        </span>
    );
}

function formatDate(date: string | null): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatDateTime(date: string | null): string {
    if (!date) return '-';
    return new Date(date).toLocaleString('en-GB', {
        day: 'numeric', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function GuestInfo({ booking }: { booking: Booking }) {
    return (
        <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
            <h2 className="mb-4 text-base font-semibold">Guest Information</h2>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Field label="Name" value={booking.guest_name} />
                <Field label="Phone" value={booking.guest_phone} />
                <Field label="Email" value={booking.guest_email} />
                <Field label="Nationality" value={booking.guest_nationality} />
                <Field label="Number of Guests" value={booking.num_guests} />
                <Field
                    label="Status"
                    value={<Badge text={booking.booking_status} color={statusColor[booking.booking_status] ?? ''} />}
                />
            </div>
        </div>
    );
}

function StayDetails({ booking }: { booking: Booking }) {
    return (
        <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
            <h2 className="mb-4 text-base font-semibold">Stay Details</h2>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Field label="Suite" value={booking.suite_name} />
                <Field label="Check-in" value={formatDate(booking.check_in)} />
                <Field label="Check-out" value={formatDate(booking.check_out)} />
                <Field label="Nights" value={booking.num_nights} />
                <Field label="Source" value={booking.booking_source} />
                <Field label="Total" value={`${booking.total_amount} ${booking.currency}`} />
                <Field label="Lodgify ID" value={booking.lodgify_booking_id} />
            </div>
            {(booking.special_requests || booking.internal_notes) && (
                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    {booking.special_requests && (
                        <div className="flex flex-col gap-0.5">
                            <span className="text-xs font-medium text-muted-foreground">Special Requests</span>
                            <p className="text-sm whitespace-pre-wrap rounded bg-muted/50 p-2">{booking.special_requests}</p>
                        </div>
                    )}
                    {booking.internal_notes && (
                        <div className="flex flex-col gap-0.5">
                            <span className="text-xs font-medium text-muted-foreground">Internal Notes</span>
                            <p className="text-sm whitespace-pre-wrap rounded bg-muted/50 p-2">{booking.internal_notes}</p>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

const stateColor: Record<string, string> = {
    waiting_preferences: 'bg-yellow-50 text-yellow-700 ring-yellow-700/10',
    preferences_partial: 'bg-blue-50 text-blue-700 ring-blue-700/10',
    preferences_complete: 'bg-green-50 text-green-700 ring-green-700/10',
};

function GuestPreferences({ booking }: { booking: Booking }) {
    const prefs = [
        { label: 'Arrival Time', value: booking.pref_arrival_time },
        { label: 'Bed Type', value: booking.pref_bed_type },
        { label: 'Airport Transfer', value: booking.pref_airport_transfer },
        { label: 'Special Requests', value: booking.pref_special_requests },
    ];

    const collected = prefs.filter((p) => p.value).length;

    return (
        <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
            <div className="mb-4 flex items-center justify-between">
                <h2 className="text-base font-semibold">Guest Preferences</h2>
                <div className="flex items-center gap-2">
                    <span className="text-xs text-muted-foreground">{collected}/4 collected</span>
                    <Badge
                        text={booking.conversation_state.replace('_', ' ')}
                        color={stateColor[booking.conversation_state] ?? ''}
                    />
                </div>
            </div>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                {prefs.map((p) => (
                    <Field
                        key={p.label}
                        label={p.label}
                        value={
                            p.value ? (
                                <span className="capitalize">{p.value}</span>
                            ) : (
                                <span className="italic text-muted-foreground">Not yet collected</span>
                            )
                        }
                    />
                ))}
            </div>
        </div>
    );
}

function ConversationLog({ messages }: { messages: WhatsappMessage[] }) {
    if (!messages || messages.length === 0) {
        return (
            <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                <h2 className="mb-2 text-base font-semibold">WhatsApp Conversation</h2>
                <p className="text-sm text-muted-foreground">No messages yet.</p>
            </div>
        );
    }

    const sorted = [...messages].sort(
        (a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime()
    );

    return (
        <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
            <h2 className="mb-4 text-base font-semibold">WhatsApp Conversation</h2>
            <div className="flex flex-col gap-2 max-h-[500px] overflow-y-auto">
                {sorted.map((msg) => (
                    <div
                        key={msg.id}
                        className={`flex ${msg.direction === 'outbound' ? 'justify-start' : 'justify-end'}`}
                    >
                        <div
                            className={`max-w-[75%] rounded-lg px-3 py-2 text-sm ${
                                msg.direction === 'outbound'
                                    ? 'bg-muted text-foreground'
                                    : 'bg-primary text-primary-foreground'
                            }`}
                        >
                            <p className="whitespace-pre-wrap break-words">{msg.message_body}</p>
                            <div className={`mt-1 flex items-center gap-2 text-[10px] ${
                                msg.direction === 'outbound' ? 'text-muted-foreground' : 'text-primary-foreground/70'
                            }`}>
                                <span>{formatDateTime(msg.sent_at ?? msg.received_at ?? msg.created_at)}</span>
                                {msg.agent_source && (
                                    <span className="rounded bg-black/10 px-1 py-0.5">{msg.agent_source}</span>
                                )}
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function UpsellHistory({ logs }: { logs: UpsellLog[] }) {
    if (!logs || logs.length === 0) {
        return (
            <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                <h2 className="mb-2 text-base font-semibold">Upsell History</h2>
                <p className="text-sm text-muted-foreground">No upsell offers sent.</p>
            </div>
        );
    }

    const sorted = [...logs].sort(
        (a, b) => new Date(b.sent_at).getTime() - new Date(a.sent_at).getTime()
    );

    return (
        <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
            <h2 className="mb-4 text-base font-semibold">Upsell History</h2>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b text-left text-xs text-muted-foreground">
                            <th className="pb-2 pr-4 font-medium">Offer</th>
                            <th className="pb-2 pr-4 font-medium">Sent</th>
                            <th className="pb-2 pr-4 font-medium">Outcome</th>
                            <th className="pb-2 pr-4 font-medium">Guest Reply</th>
                            <th className="pb-2 font-medium">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        {sorted.map((log) => (
                            <tr key={log.id} className="border-b last:border-0">
                                <td className="py-2 pr-4">{log.offer?.title ?? `Offer #${log.offer_id}`}</td>
                                <td className="py-2 pr-4 text-muted-foreground">{formatDateTime(log.sent_at)}</td>
                                <td className="py-2 pr-4">
                                    {log.outcome ? (
                                        <Badge text={log.outcome} color={upsellOutcomeColor[log.outcome] ?? 'bg-gray-50 text-gray-600 ring-gray-600/10'} />
                                    ) : '-'}
                                </td>
                                <td className="py-2 pr-4 max-w-[200px] truncate">{log.guest_reply ?? '-'}</td>
                                <td className="py-2">{log.revenue_generated ? `${log.revenue_generated} MAD` : '-'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

type Props = { booking?: Booking };

export default function BookingDetail({ booking }: Props) {
    if (!booking) {
        return (
            <div className="flex flex-col items-center justify-center gap-4 py-20">
                <p className="text-muted-foreground">Booking not found.</p>
                <Button variant="outline" onClick={() => router.visit(index().url)}>Back to Bookings</Button>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center gap-3">
                <Button variant="ghost" size="icon" onClick={() => router.visit(index().url)}>
                    <ArrowLeft className="size-4" />
                </Button>
                <div>
                    <h1 className="text-xl font-semibold">{booking.guest_name}</h1>
                    <p className="text-sm text-muted-foreground">
                        {booking.suite_name} &middot; {formatDate(booking.check_in)} - {formatDate(booking.check_out)}
                    </p>
                </div>
            </div>
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <GuestInfo booking={booking} />
                <StayDetails booking={booking} />
            </div>
            <GuestPreferences booking={booking} />
            <ConversationLog messages={booking.whatsapp_messages ?? []} />
            <UpsellHistory logs={booking.upsell_logs ?? []} />
        </div>
    );
}

BookingDetail.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
