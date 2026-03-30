import { createColumnHelper, type ColumnDef } from '@tanstack/react-table';
import { useCallback } from 'react';

import NextTable from '@/components/next-table';
import AppLayout from '@/layouts/app-layout';
import { createDateColumn } from '@/lib/column-helpers';
import { fetch as fetchRoute } from '@/routes/backoffice/concierge/system-log';
import type { SystemLog } from '@/types/system-log';

const helper = createColumnHelper<SystemLog>();

const statusColor: Record<string, string> = {
    success: 'bg-green-50 text-green-700 ring-green-700/10',
    failed: 'bg-red-50 text-red-700 ring-red-700/10',
    skipped: 'bg-yellow-50 text-yellow-700 ring-yellow-700/10',
};

const columns: ColumnDef<SystemLog, any>[] = [
    createDateColumn<SystemLog>('created_at', 'Time'),
    helper.display({
        id: 'agent',
        header: 'Agent',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => (
            <span className="inline-flex items-center rounded-full bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                {ctx.row.original.agent}
            </span>
        ),
    }),
    helper.accessor('action', {
        id: 'action',
        header: 'Action',
        enableColumnFilter: false,
        enableHiding: false,
    }),
    helper.display({
        id: 'status',
        header: 'Status',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => {
            const status = ctx.row.original.status;
            return (
                <span className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset ${statusColor[status] ?? ''}`}>
                    {status}
                </span>
            );
        },
    }),
    helper.display({
        id: 'booking',
        header: 'Booking',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => ctx.row.original.booking?.guest_name ?? '-',
    }),
    helper.display({
        id: 'duration_ms',
        header: 'Duration',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => ctx.row.original.duration_ms ? `${ctx.row.original.duration_ms}ms` : '-',
    }),
    helper.display({
        id: 'error_message',
        header: 'Error',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => {
            const err = ctx.row.original.error_message;
            if (!err) return '-';
            return <span className="text-red-600">{err.length > 80 ? err.substring(0, 80) + '...' : err}</span>;
        },
    }),
];

export default function SystemLogIndex() {
    const load = useCallback(async (params: Record<string, any>) => {
        const query = new URLSearchParams(params).toString();
        const res = await fetch(fetchRoute({ query: params }).url);
        return res.json();
    }, []);

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-col">
                <h1 className="text-xl font-semibold">System Logs</h1>
                <p className="hidden text-sm text-gray-500 sm:block">Agent action audit trail</p>
            </div>
            <NextTable<SystemLog>
                load={load}
                id={'id' as keyof SystemLog}
                columns={columns}
                mode="table"
            />
        </div>
    );
}

SystemLogIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
