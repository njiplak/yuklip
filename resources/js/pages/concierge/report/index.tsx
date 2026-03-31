import { useCallback, useEffect, useState } from 'react';
import { Download } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { fetch as fetchRoute, exportMethod as exportRoute } from '@/routes/backoffice/concierge/report';

type Summary = {
    total_bookings: number;
    accommodation_revenue: number;
    upsell_revenue: number;
    total_revenue: number;
    total_expenses: number;
    net_revenue: number;
    occupancy_rate: number;
    cancellations: number;
};

type BreakdownRow = {
    label: string;
    total: number;
    count: number;
};

type UpsellMetrics = {
    total_sent: number;
    accepted: number;
    declined: number;
    pending: number;
    conversion_rate: number;
    revenue: number;
};

type ReportData = {
    period: { from: string; to: string };
    summary: Summary;
    revenue_by_suite: BreakdownRow[];
    revenue_by_source: BreakdownRow[];
    upsell: UpsellMetrics;
};

function today(): string {
    return new Date().toISOString().split('T')[0];
}

function startOfMonth(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`;
}

function StatCard({ label, value, sub }: { label: string; value: string; sub?: string }) {
    return (
        <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-2xl font-bold">{value}</p>
            {sub && <p className="mt-0.5 text-xs text-muted-foreground">{sub}</p>}
        </div>
    );
}

function BreakdownTable({ title, rows, currency }: { title: string; rows: BreakdownRow[]; currency?: string }) {
    if (rows.length === 0) {
        return null;
    }

    return (
        <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
            <h3 className="mb-3 text-sm font-semibold">{title}</h3>
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-xs text-muted-foreground">
                        <th className="pb-2 font-medium">Name</th>
                        <th className="pb-2 text-right font-medium">Bookings</th>
                        <th className="pb-2 text-right font-medium">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.label} className="border-b last:border-0">
                            <td className="py-2">{row.label}</td>
                            <td className="py-2 text-right text-muted-foreground">{row.count}</td>
                            <td className="py-2 text-right font-medium">{fmt(row.total)} {currency ?? 'MAD'}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function fmt(n: number): string {
    return n.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function downloadCSV(data: Record<string, unknown>[], filename: string) {
    if (data.length === 0) return;

    const headers = Object.keys(data[0]);
    const rows = data.map((row) => headers.map((h) => String(row[h] ?? '')).join(','));
    const csv = [headers.join(','), ...rows].join('\n');

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}

export default function ReportIndex() {
    const [from, setFrom] = useState(startOfMonth());
    const [to, setTo] = useState(today());
    const [data, setData] = useState<ReportData | null>(null);
    const [loading, setLoading] = useState(false);

    const loadReport = useCallback(async () => {
        setLoading(true);
        try {
            const res = await fetch(fetchRoute({ query: { from, to } }).url);
            setData(await res.json());
        } finally {
            setLoading(false);
        }
    }, [from, to]);

    useEffect(() => {
        loadReport();
    }, [loadReport]);

    const handleExport = async () => {
        const res = await fetch(exportRoute({ query: { from, to } }).url);
        const transactions = await res.json();
        downloadCSV(transactions, `report-${from}-to-${to}.csv`);
    };

    const setPreset = (preset: string) => {
        const now = new Date();
        switch (preset) {
            case 'this_week': {
                const start = new Date(now);
                start.setDate(now.getDate() - now.getDay() + 1);
                setFrom(start.toISOString().split('T')[0]);
                setTo(today());
                break;
            }
            case 'this_month':
                setFrom(startOfMonth());
                setTo(today());
                break;
            case 'last_month': {
                const lastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                const lastDay = new Date(now.getFullYear(), now.getMonth(), 0);
                setFrom(lastMonth.toISOString().split('T')[0]);
                setTo(lastDay.toISOString().split('T')[0]);
                break;
            }
        }
    };

    const s = data?.summary;

    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Reports</h1>
                    <p className="text-sm text-muted-foreground">Financial and performance metrics</p>
                </div>
                <div className="flex flex-wrap items-end gap-2">
                    <div className="flex gap-1">
                        <Button variant="outline" size="sm" onClick={() => setPreset('this_week')}>This Week</Button>
                        <Button variant="outline" size="sm" onClick={() => setPreset('this_month')}>This Month</Button>
                        <Button variant="outline" size="sm" onClick={() => setPreset('last_month')}>Last Month</Button>
                    </div>
                    <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="w-36" />
                    <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="w-36" />
                    <Button variant="outline" size="sm" onClick={handleExport}>
                        <Download className="mr-1 size-4" /> CSV
                    </Button>
                </div>
            </div>

            {loading && <p className="text-sm text-muted-foreground">Loading...</p>}

            {s && (
                <>
                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        <StatCard label="Total Bookings" value={String(s.total_bookings)} />
                        <StatCard label="Accommodation Revenue" value={`${fmt(s.accommodation_revenue)} MAD`} />
                        <StatCard label="Upsell Revenue" value={`${fmt(s.upsell_revenue)} MAD`} />
                        <StatCard label="Total Revenue" value={`${fmt(s.total_revenue)} MAD`} />
                    </div>

                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        <StatCard label="Total Expenses" value={`${fmt(s.total_expenses)} MAD`} />
                        <StatCard
                            label="Net Revenue"
                            value={`${fmt(s.net_revenue)} MAD`}
                            sub={s.net_revenue >= 0 ? 'Profit' : 'Loss'}
                        />
                        <StatCard label="Occupancy Rate" value={`${s.occupancy_rate}%`} sub={`${Math.round(s.occupancy_rate * 4 * ((new Date(to).getTime() - new Date(from).getTime()) / 86400000 + 1) / 100)} room-nights booked`} />
                        <StatCard label="Cancellations" value={String(s.cancellations)} />
                    </div>

                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <BreakdownTable title="Revenue by Suite" rows={data!.revenue_by_suite} />
                        <BreakdownTable title="Revenue by Source" rows={data!.revenue_by_source} />
                    </div>

                    {data!.upsell.total_sent > 0 && (
                        <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                            <h3 className="mb-3 text-sm font-semibold">Upsell Performance</h3>
                            <div className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3 lg:grid-cols-6">
                                <div>
                                    <p className="text-xs text-muted-foreground">Sent</p>
                                    <p className="text-lg font-bold">{data!.upsell.total_sent}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">Accepted</p>
                                    <p className="text-lg font-bold text-green-600">{data!.upsell.accepted}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">Declined</p>
                                    <p className="text-lg font-bold text-red-600">{data!.upsell.declined}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">Pending</p>
                                    <p className="text-lg font-bold text-yellow-600">{data!.upsell.pending}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">Conversion Rate</p>
                                    <p className="text-lg font-bold">{data!.upsell.conversion_rate}%</p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">Upsell Revenue</p>
                                    <p className="text-lg font-bold">{fmt(data!.upsell.revenue)} MAD</p>
                                </div>
                            </div>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}

ReportIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
