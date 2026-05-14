import { useEffect, useState } from 'react';
import { FileText, Sparkles } from 'lucide-react';

import AppLayout from '@/layouts/app-layout';

type ReportSavings = {
    amount: number | null;
    traditional_rate: number | null;
};

type ReportSummary = {
    accommodation_revenue: number | null;
    upsell_revenue: number | null;
    expenses: number | null;
    concierge_fee: number | null;
    concierge_fee_rate: number | null;
    net_profit: number | null;
};

type WeeklyReport = {
    label: string | null;
    subtitle: string | null;
    revenue: number | null;
    week_start: string | null;
    week_end: string | null;
};

type FinancialReport = {
    period: { month: string | null; year: number | null } | null;
    savings: ReportSavings | null;
    summary: ReportSummary | null;
    currency: string | null;
    weekly_reports: WeeklyReport[];
};

function fmtMoney(value: number | null | undefined, currency: string, opts: { negative?: boolean } = {}): string {
    const sign = currency === 'EUR' ? '€' : currency;
    if (value == null) return `— ${sign}`;
    const abs = Math.round(Math.abs(value));
    const grouped = abs.toLocaleString('fr-FR').replace(/,/g, ' ');
    const isNegative = opts.negative || value < 0;
    return `${isNegative ? '- ' : ''}${grouped} ${sign}`;
}

export default function FinancialIndex() {
    const [data, setData] = useState<FinancialReport | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        fetch('/concierge/financial/fetch', { headers: { Accept: 'application/json' } })
            .then(async (res) => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const body = await res.json();
                // WebResponse::json wraps payload — handle both shapes.
                return (body?.data ?? body) as FinancialReport;
            })
            .then((report) => {
                if (cancelled) return;
                setData(report);
                setError(null);
            })
            .catch((err) => {
                if (cancelled) return;
                setError(err instanceof Error ? err.message : 'Failed to load report');
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });
        return () => {
            cancelled = true;
        };
    }, []);

    const currency = data?.currency ?? 'EUR';
    const periodLabel = data?.period
        ? [data.period.month, data.period.year].filter(Boolean).join(' ')
        : '';
    const summaryTitle = periodLabel ? `${periodLabel} summary` : 'Summary';
    const savingsAmount = fmtMoney(data?.savings?.amount, currency);
    const traditionalRate = data?.savings?.traditional_rate ?? 20;
    const conciergeFeeRate = data?.summary?.concierge_fee_rate ?? 15;
    const latestWeekly = data?.weekly_reports?.[0];

    return (
        <div className="mx-auto flex w-full max-w-md flex-col gap-4 px-4 py-6">
            <header className="flex items-center gap-3">
                <span className="flex size-7 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-500">
                    <Sparkles className="size-4" />
                </span>
                <span className="text-lg font-bold">
                    Yasmine <span className="text-orange-500">AI</span>
                </span>
                <span className="ml-1 inline-flex items-center gap-1.5 rounded-full bg-emerald-500/20 px-2.5 py-0.5 text-xs font-medium text-emerald-500">
                    <span className="size-1.5 rounded-full bg-emerald-500" />
                    Active · 47 msgs
                </span>
            </header>

            {loading && !data && (
                <p className="text-sm text-muted-foreground">Loading…</p>
            )}
            {error && !data && (
                <p className="text-sm text-red-500">{error}</p>
            )}

            <div
                className="rounded-2xl p-5 text-white"
                style={{
                    backgroundImage: 'linear-gradient(135deg, #2FA980 0%, #1F8F66 100%)',
                }}
            >
                <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-white/80">
                    Savings vs traditional agency
                </p>
                <p className="mt-2 text-3xl font-extrabold tracking-tight">{savingsAmount}</p>
                <p className="mt-1 text-xs leading-relaxed text-white/80">
                    This month, Yasmine saved you {savingsAmount}
                    <br />
                    vs {traditionalRate}% agency + accountant
                </p>
            </div>

            <h2 className="mt-2 text-base font-extrabold">{summaryTitle}</h2>

            <div className="rounded-2xl bg-white px-4 text-black shadow-sm">
                <SummaryRow
                    label="Accommodation revenue"
                    value={fmtMoney(data?.summary?.accommodation_revenue, currency)}
                    valueClass="text-emerald-600"
                />
                <SummaryRow
                    label="Yasmine upsells"
                    value={fmtMoney(data?.summary?.upsell_revenue, currency)}
                    valueClass="text-emerald-600"
                />
                <SummaryRow
                    label="Expenses"
                    value={fmtMoney(data?.summary?.expenses, currency, { negative: true })}
                    valueClass="text-orange-500"
                />
                <SummaryRow
                    label={`E-Conciergerie fee (${conciergeFeeRate}%)`}
                    value={fmtMoney(data?.summary?.concierge_fee, currency, { negative: true })}
                    valueClass="text-orange-500"
                />
                <SummaryRow
                    label="Net profit"
                    value={fmtMoney(data?.summary?.net_profit, currency)}
                    valueClass="text-emerald-600"
                    emphasize
                    last
                />
            </div>

            <div className="flex items-center gap-3 rounded-2xl bg-white p-4 text-black shadow-sm">
                <div className="flex-1">
                    <p className="text-sm font-extrabold">{latestWeekly?.label ?? 'Weekly report'}</p>
                    <p className="mt-0.5 text-xs text-black/60">
                        {latestWeekly?.subtitle ?? 'Revenue · Upsells · Occupancy'}
                    </p>
                </div>
                <button
                    type="button"
                    className="inline-flex items-center gap-1.5 rounded-lg bg-[#D94A2B] px-3.5 py-2 text-xs font-extrabold text-white"
                >
                    <FileText className="size-4" />
                    PDF
                </button>
            </div>
        </div>
    );
}

function SummaryRow({
    label,
    value,
    valueClass,
    emphasize = false,
    last = false,
}: {
    label: string;
    value: string;
    valueClass: string;
    emphasize?: boolean;
    last?: boolean;
}) {
    return (
        <div
            className={
                'flex items-center justify-between py-3 ' +
                (last ? '' : 'border-b border-black/[0.06]')
            }
        >
            <span className={'text-sm ' + (emphasize ? 'font-extrabold' : 'font-medium text-black/80')}>
                {label}
            </span>
            <span className={'text-sm font-bold ' + valueClass + (emphasize ? ' text-base' : '')}>
                {value}
            </span>
        </div>
    );
}

FinancialIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
