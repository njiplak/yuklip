import { useCallback, useEffect, useState } from 'react';
import { ChevronLeft, ChevronRight, FileText } from 'lucide-react';

import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { fetch as fetchRoute } from '@/routes/backoffice/concierge/financial-report';

type Summary = {
    accommodation_revenue: number;
    upsell_revenue: number;
    expenses: number;
    concierge_fee: number;
    concierge_fee_rate: number;
    net_profit: number;
};

type WeeklyReport = {
    label: string;
    subtitle: string;
    revenue: number;
    week_start: string;
    week_end: string;
};

type FinancialData = {
    period: { month: string; year: number };
    savings: { amount: number; traditional_rate: number };
    summary: Summary;
    currency: string;
    weekly_reports: WeeklyReport[];
};

function fmt(n: number): string {
    return Math.abs(n).toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

export default function FinancialReportIndex() {
    const now = new Date();
    const [month, setMonth] = useState(now.getMonth() + 1);
    const [year, setYear] = useState(now.getFullYear());
    const [data, setData] = useState<FinancialData | null>(null);
    const [loading, setLoading] = useState(false);

    const loadData = useCallback(async () => {
        setLoading(true);
        try {
            const res = await fetch(fetchRoute({ query: { month: String(month), year: String(year) } }).url);
            setData(await res.json());
        } finally {
            setLoading(false);
        }
    }, [month, year]);

    useEffect(() => {
        loadData();
    }, [loadData]);

    function prevMonth() {
        if (month === 1) {
            setMonth(12);
            setYear(year - 1);
        } else {
            setMonth(month - 1);
        }
    }

    function nextMonth() {
        const isCurrentMonth = month === now.getMonth() + 1 && year === now.getFullYear();
        if (isCurrentMonth) return;
        if (month === 12) {
            setMonth(1);
            setYear(year + 1);
        } else {
            setMonth(month + 1);
        }
    }

    const isCurrentMonth = month === now.getMonth() + 1 && year === now.getFullYear();
    const s = data?.summary;
    const cur = data?.currency ?? 'EUR';
    const sym = cur === 'EUR' ? '\u20AC' : cur === 'MAD' ? 'MAD' : cur;

    return (
        <div className="mx-auto flex max-w-2xl flex-col gap-6">
            {/* Month navigation */}
            <div className="flex items-center justify-between">
                <Button variant="ghost" size="icon" onClick={prevMonth}>
                    <ChevronLeft className="size-5" />
                </Button>
                <h2 className="text-lg font-semibold">
                    {data?.period.month ?? ''} {data?.period.year ?? year}
                </h2>
                <Button variant="ghost" size="icon" onClick={nextMonth} disabled={isCurrentMonth}>
                    <ChevronRight className="size-5" />
                </Button>
            </div>

            {loading && <p className="text-center text-sm text-muted-foreground">Loading...</p>}

            {s && data && (
                <>
                    {/* Savings card */}
                    <div className="rounded-2xl bg-gradient-to-br from-emerald-600 to-emerald-800 p-5 text-white shadow-lg">
                        <p className="text-[11px] font-semibold uppercase tracking-widest text-emerald-200">
                            Savings vs Traditional Agency
                        </p>
                        <p className="mt-2 text-4xl font-bold tracking-tight">
                            {fmt(data.savings.amount)} {sym}
                        </p>
                        <p className="mt-1.5 text-sm text-emerald-100">
                            This month, Yasmine saved you {fmt(data.savings.amount)} {sym} vs {data.savings.traditional_rate}% agency + accountant
                        </p>
                    </div>

                    {/* Monthly summary */}
                    <div>
                        <h3 className="mb-4 text-lg font-bold">
                            {data.period.month} {data.period.year} summary
                        </h3>
                        <div className="rounded-xl border border-border bg-card shadow-sm">
                            <SummaryRow label="Accommodation revenue" amount={s.accommodation_revenue} currency={sym} />
                            <SummaryRow label="Yasmine upsells" amount={s.upsell_revenue} currency={sym} />
                            <SummaryRow label="Expenses" amount={-s.expenses} currency={sym} negative />
                            <SummaryRow
                                label={`E-Conciergerie fee (${s.concierge_fee_rate}%)`}
                                amount={-s.concierge_fee}
                                currency={sym}
                                negative
                            />
                            <SummaryRow
                                label="Net profit"
                                amount={s.net_profit}
                                currency={sym}
                                bold
                                positive={s.net_profit >= 0}
                                last
                            />
                        </div>
                    </div>

                    {/* Weekly reports */}
                    {data.weekly_reports.length > 0 && (
                        <div className="flex flex-col gap-3">
                            {data.weekly_reports.map((report) => (
                                <div
                                    key={report.week_start}
                                    className="flex items-center justify-between rounded-xl border border-border bg-card p-4 shadow-sm"
                                >
                                    <div>
                                        <p className="text-sm font-semibold">{report.label}</p>
                                        <p className="text-xs text-muted-foreground">{report.subtitle}</p>
                                    </div>
                                    <Button variant="outline" size="sm">
                                        <FileText className="mr-1.5 size-4" />
                                        PDF
                                    </Button>
                                </div>
                            ))}
                        </div>
                    )}
                </>
            )}
        </div>
    );
}

function SummaryRow({
    label,
    amount,
    currency,
    negative = false,
    positive = false,
    bold = false,
    last = false,
}: {
    label: string;
    amount: number;
    currency: string;
    negative?: boolean;
    positive?: boolean;
    bold?: boolean;
    last?: boolean;
}) {
    const sign = amount < 0 ? '- ' : '';
    const display = `${sign}${fmt(amount)} ${currency}`;

    return (
        <div
            className={cn(
                'flex items-center justify-between px-4 py-3',
                !last && 'border-b border-border',
            )}
        >
            <span className={cn('text-sm', bold && 'font-bold')}>{label}</span>
            <span
                className={cn(
                    'text-sm font-semibold tabular-nums',
                    negative && 'text-red-500',
                    positive && 'text-emerald-600',
                    bold && 'text-base font-bold',
                    !negative && !positive && !bold && 'text-emerald-600',
                )}
            >
                {display}
            </span>
        </div>
    );
}

FinancialReportIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
