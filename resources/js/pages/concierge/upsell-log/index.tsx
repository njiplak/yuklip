import { createColumnHelper, type ColumnDef } from '@tanstack/react-table';
import { useCallback } from 'react';

import NextTable from '@/components/next-table';
import AppLayout from '@/layouts/app-layout';
import { createDateColumn } from '@/lib/column-helpers';
import { fetch as fetchRoute } from '@/routes/backoffice/concierge/upsell-log';
import type { UpsellLog } from '@/types/booking';

const helper = createColumnHelper<UpsellLog>();

const outcomeColor: Record<string, string> = {
    accepted: 'bg-green-50 text-green-700 ring-green-700/10',
    declined: 'bg-red-50 text-red-700 ring-red-700/10',
    pending: 'bg-yellow-50 text-yellow-700 ring-yellow-700/10',
    no_reply: 'bg-gray-50 text-gray-600 ring-gray-600/10',
};

const columns: ColumnDef<UpsellLog, any>[] = [
    helper.display({
        id: 'guest',
        header: 'Guest',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => ctx.row.original.booking?.guest_name ?? '-',
    }),
    helper.display({
        id: 'offer',
        header: 'Offer',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => ctx.row.original.offer?.title ?? '-',
    }),
    createDateColumn<UpsellLog>('sent_at', 'Sent At'),
    helper.display({
        id: 'outcome',
        header: 'Outcome',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => {
            const outcome = ctx.row.original.outcome;
            if (!outcome) return '-';
            return (
                <span className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset ${outcomeColor[outcome] ?? ''}`}>
                    {outcome.replace('_', ' ')}
                </span>
            );
        },
    }),
    helper.display({
        id: 'revenue',
        header: 'Revenue',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => ctx.row.original.revenue_generated ? `${ctx.row.original.revenue_generated} MAD` : '-',
    }),
    helper.display({
        id: 'guest_reply',
        header: 'Guest Reply',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => {
            const reply = ctx.row.original.guest_reply;
            if (!reply) return '-';
            return reply.length > 80 ? reply.substring(0, 80) + '...' : reply;
        },
    }),
];

export default function UpsellLogIndex() {
    const load = useCallback(async (params: Record<string, any>) => {
        const query = new URLSearchParams(params).toString();
        const res = await fetch(fetchRoute({ query: params }).url);
        return res.json();
    }, []);

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-col">
                <h1 className="text-xl font-semibold">Upsell Logs</h1>
                <p className="hidden text-sm text-gray-500 sm:block">Monitor upsell performance</p>
            </div>
            <NextTable<UpsellLog>
                load={load}
                id={'id' as keyof UpsellLog}
                columns={columns}
                mode="table"
            />
        </div>
    );
}

UpsellLogIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
