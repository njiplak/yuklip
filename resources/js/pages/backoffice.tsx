import { Link } from '@inertiajs/react';
import { AlertTriangle, CalendarCheck, LogOut as LogOutIcon, Plane, Users } from 'lucide-react';

import AppLayout from '@/layouts/app-layout';
import { show } from '@/routes/backoffice/concierge/booking';

type Arrival = {
    id: number;
    guest_name: string;
    suite_name: string;
    num_guests: number;
    guest_nationality: string | null;
    guest_phone: string;
    pref_arrival_time: string | null;
    pref_airport_transfer: string | null;
    conversation_state: string;
};

type Departure = {
    id: number;
    guest_name: string;
    suite_name: string;
};

type Stats = {
    arrivals: number;
    departures: number;
    checked_in: number;
    pending_preferences: number;
    handover_count: number;
    recent_errors: number;
};

type Props = {
    stats: Stats;
    arrivals: Arrival[];
    departures: Departure[];
};

const stateLabel: Record<string, string> = {
    waiting_preferences: 'Awaiting reply',
    preferences_partial: 'Collecting...',
    preferences_complete: 'Ready',
    handover_human: 'Needs attention',
};

const stateColor: Record<string, string> = {
    waiting_preferences: 'text-yellow-600',
    preferences_partial: 'text-blue-600',
    preferences_complete: 'text-green-600',
    handover_human: 'text-red-600',
};

function StatCard({ label, value, icon: Icon, alert }: { label: string; value: number; icon: React.ElementType; alert?: boolean }) {
    return (
        <div className={`flex items-center gap-4 rounded-xl border p-4 shadow-sm ${alert && value > 0 ? 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950' : 'border-border bg-card'}`}>
            <div className={`flex size-10 items-center justify-center rounded-lg ${alert && value > 0 ? 'bg-red-100 text-red-600 dark:bg-red-900' : 'bg-muted text-muted-foreground'}`}>
                <Icon className="size-5" />
            </div>
            <div>
                <p className="text-2xl font-bold">{value}</p>
                <p className="text-xs text-muted-foreground">{label}</p>
            </div>
        </div>
    );
}

export default function Backoffice({ stats, arrivals, departures }: Props) {
    return (
        <div className="flex flex-col gap-6">
            <div>
                <h1 className="text-xl font-semibold">Dashboard</h1>
                <p className="text-sm text-muted-foreground">Today's operational overview</p>
            </div>

            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard label="Arrivals today" value={stats.arrivals} icon={CalendarCheck} />
                <StatCard label="Departures today" value={stats.departures} icon={LogOutIcon} />
                <StatCard label="Currently checked in" value={stats.checked_in} icon={Users} />
                <StatCard label="Needs attention" value={stats.handover_count + stats.recent_errors} icon={AlertTriangle} alert />
            </div>

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                {/* Arrivals */}
                <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <h2 className="mb-4 text-base font-semibold">Arrivals Today</h2>
                    {arrivals.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No arrivals today.</p>
                    ) : (
                        <div className="flex flex-col gap-3">
                            {arrivals.map((a) => (
                                <Link
                                    key={a.id}
                                    href={show(a.id).url}
                                    className="flex items-center justify-between rounded-lg border border-border p-3 transition-colors hover:bg-muted/50"
                                >
                                    <div className="flex flex-col gap-0.5">
                                        <span className="text-sm font-medium">{a.guest_name}</span>
                                        <span className="text-xs text-muted-foreground">
                                            {a.suite_name} &middot; {a.num_guests} guest{a.num_guests > 1 ? 's' : ''}
                                            {a.guest_nationality ? ` &middot; ${a.guest_nationality}` : ''}
                                        </span>
                                    </div>
                                    <div className="flex flex-col items-end gap-0.5">
                                        {a.pref_arrival_time && (
                                            <span className="text-xs font-medium">{a.pref_arrival_time}</span>
                                        )}
                                        {a.pref_airport_transfer === 'yes' && (
                                            <span className="inline-flex items-center gap-1 text-xs text-blue-600">
                                                <Plane className="size-3" /> Transfer
                                            </span>
                                        )}
                                        <span className={`text-xs ${stateColor[a.conversation_state] ?? ''}`}>
                                            {stateLabel[a.conversation_state] ?? a.conversation_state}
                                        </span>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>

                {/* Departures */}
                <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <h2 className="mb-4 text-base font-semibold">Departures Today</h2>
                    {departures.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No departures today.</p>
                    ) : (
                        <div className="flex flex-col gap-3">
                            {departures.map((d) => (
                                <Link
                                    key={d.id}
                                    href={show(d.id).url}
                                    className="flex items-center justify-between rounded-lg border border-border p-3 transition-colors hover:bg-muted/50"
                                >
                                    <span className="text-sm font-medium">{d.guest_name}</span>
                                    <span className="text-xs text-muted-foreground">{d.suite_name}</span>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {(stats.pending_preferences > 0 || stats.handover_count > 0 || stats.recent_errors > 0) && (
                <div className="rounded-xl border border-yellow-200 bg-yellow-50 p-5 shadow-sm dark:border-yellow-900 dark:bg-yellow-950">
                    <h2 className="mb-3 text-base font-semibold">Attention Needed</h2>
                    <div className="flex flex-col gap-2 text-sm">
                        {stats.pending_preferences > 0 && (
                            <p>{stats.pending_preferences} guest{stats.pending_preferences > 1 ? 's' : ''} still collecting preferences</p>
                        )}
                        {stats.handover_count > 0 && (
                            <p className="font-medium text-red-600">{stats.handover_count} guest{stats.handover_count > 1 ? 's' : ''} escalated to manager — bot is paused</p>
                        )}
                        {stats.recent_errors > 0 && (
                            <p className="text-red-600">{stats.recent_errors} error{stats.recent_errors > 1 ? 's' : ''} in the last 24 hours — check System Logs</p>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

Backoffice.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
