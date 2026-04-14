import { useCallback, useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, RefreshCw } from 'lucide-react';

import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { fetch as fetchRoute } from '@/routes/backoffice/concierge/alert';
import backoffice from '@/routes/backoffice';

type Alert = {
    id: string;
    type: 'cancellation' | 'escalation';
    title: string;
    details: string;
    amount?: number;
    currency?: string;
    booking_id?: number;
    created_at: string;
};

type AlertData = {
    alerts: Alert[];
};

function fmt(n: number): string {
    return Math.abs(n).toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function timeAgo(iso: string): string {
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 60) return `${mins}m ago`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    return `${days}d ago`;
}

export default function AlertIndex() {
    const [data, setData] = useState<AlertData | null>(null);
    const [loading, setLoading] = useState(false);

    const loadData = useCallback(async () => {
        setLoading(true);
        try {
            const res = await fetch(fetchRoute().url);
            setData(await res.json());
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        loadData();
    }, [loadData]);

    const alerts = data?.alerts ?? [];
    const cancellations = alerts.filter((a) => a.type === 'cancellation');
    const escalations = alerts.filter((a) => a.type === 'escalation');

    return (
        <div className="mx-auto flex max-w-2xl flex-col gap-6">
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-bold">Active alerts</h2>
                <Button variant="ghost" size="icon" onClick={loadData} disabled={loading}>
                    <RefreshCw className={cn('size-4', loading && 'animate-spin')} />
                </Button>
            </div>

            {loading && !data && (
                <p className="text-center text-sm text-muted-foreground">Loading...</p>
            )}

            {data && alerts.length === 0 && <AllClear />}

            {cancellations.map((alert) => (
                <CancellationCard key={alert.id} alert={alert} />
            ))}

            {escalations.map((alert) => (
                <EscalationCard key={alert.id} alert={alert} />
            ))}

            {data && alerts.length > 0 && <AllClear partial />}
        </div>
    );
}

function CancellationCard({ alert }: { alert: Alert }) {
    const cur = alert.currency ?? 'EUR';
    const sym = cur === 'EUR' ? '\u20AC' : cur === 'MAD' ? 'MAD' : cur;

    return (
        <div className="overflow-hidden rounded-2xl border border-red-200 bg-red-50 shadow-sm dark:border-red-900 dark:bg-red-950/30">
            <div className="p-5">
                <div className="mb-3 flex items-start justify-between">
                    <div>
                        <span className="mb-1.5 inline-block rounded-full bg-red-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-widest text-red-700 dark:bg-red-900/50 dark:text-red-400">
                            Cancellation
                        </span>
                        <h3 className="mt-1 text-base font-bold text-foreground">{alert.title}</h3>
                        <p className="text-sm text-muted-foreground">
                            {alert.details}
                            <span className="ml-2 text-xs opacity-60">{timeAgo(alert.created_at)}</span>
                        </p>
                    </div>
                    {alert.amount != null && (
                        <span className="text-lg font-bold text-red-600 dark:text-red-400">
                            - {fmt(alert.amount)} {sym}
                        </span>
                    )}
                </div>
            </div>
            {alert.booking_id && (
                <div className="border-t border-red-200 bg-red-100/50 px-5 py-3 dark:border-red-900 dark:bg-red-950/20">
                    <Link
                        href={backoffice.concierge.booking.index.url() + '/' + alert.booking_id}
                        className="inline-flex items-center gap-1.5 text-sm font-semibold text-red-700 transition-colors hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                    >
                        View booking <ArrowRight className="size-4" />
                    </Link>
                </div>
            )}
        </div>
    );
}

function EscalationCard({ alert }: { alert: Alert }) {
    return (
        <div className="overflow-hidden rounded-2xl border border-amber-200 bg-amber-50 shadow-sm dark:border-amber-900 dark:bg-amber-950/30">
            <div className="p-5">
                <span className="mb-1.5 inline-block rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-widest text-amber-700 dark:bg-amber-900/50 dark:text-amber-400">
                    Manager Escalation
                </span>
                <h3 className="mt-1 text-base font-bold text-foreground">{alert.title}</h3>
                <p className="text-sm text-muted-foreground">
                    {alert.details}
                    <span className="ml-2 text-xs opacity-60">{timeAgo(alert.created_at)}</span>
                </p>
            </div>
            {alert.booking_id && (
                <div className="border-t border-amber-200 bg-amber-100/50 px-5 py-3 dark:border-amber-900 dark:bg-amber-950/20">
                    <Link
                        href={backoffice.concierge.booking.index.url() + '/' + alert.booking_id}
                        className="inline-flex items-center gap-1.5 text-sm font-semibold text-amber-700 transition-colors hover:text-amber-900 dark:text-amber-400 dark:hover:text-amber-300"
                    >
                        View booking <ArrowRight className="size-4" />
                    </Link>
                </div>
            )}
        </div>
    );
}

function AllClear({ partial = false }: { partial?: boolean }) {
    return (
        <div className="flex flex-col items-center gap-2 rounded-2xl border border-border bg-card py-8 text-center shadow-sm">
            <CheckCircle2 className="size-10 text-emerald-500" />
            <p className="text-base font-semibold">
                {partial ? 'Everything else under control' : 'All clear'}
            </p>
            <p className="text-sm text-muted-foreground">
                {partial ? 'No other active alerts' : 'No active alerts in the last 7 days'}
            </p>
        </div>
    );
}

AlertIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
