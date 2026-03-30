import { createColumnHelper, type ColumnDef } from '@tanstack/react-table';
import { useCallback, useState } from 'react';

import NextTable from '@/components/next-table';
import AppLayout from '@/layouts/app-layout';
import { createDateColumn } from '@/lib/column-helpers';
import { fetch as fetchRoute } from '@/routes/backoffice/concierge/webhook-log';
import type { WebhookLog } from '@/types/webhook-log';

const helper = createColumnHelper<WebhookLog>();

const sourceColor: Record<string, string> = {
    lodgify: 'bg-purple-50 text-purple-700 ring-purple-700/10',
    whatsapp: 'bg-green-50 text-green-700 ring-green-700/10',
};

const statusColor = (code: number | null): string => {
    if (!code) return 'bg-gray-50 text-gray-700 ring-gray-700/10';
    if (code >= 200 && code < 300) return 'bg-green-50 text-green-700 ring-green-700/10';
    if (code >= 400) return 'bg-red-50 text-red-700 ring-red-700/10';
    return 'bg-yellow-50 text-yellow-700 ring-yellow-700/10';
};

function JsonViewer({ data, label }: { data: Record<string, unknown> | null; label: string }) {
    const [open, setOpen] = useState(false);

    if (!data) return <span className="text-gray-400">-</span>;

    return (
        <div>
            <button
                onClick={() => setOpen(!open)}
                className="text-xs font-medium text-blue-600 hover:text-blue-800"
            >
                {open ? 'Hide' : 'View'} {label}
            </button>
            {open && (
                <pre className="mt-1 max-h-60 overflow-auto rounded bg-gray-50 p-2 text-xs text-gray-800 dark:bg-gray-900 dark:text-gray-200">
                    {JSON.stringify(data, null, 2)}
                </pre>
            )}
        </div>
    );
}

const columns: ColumnDef<WebhookLog, any>[] = [
    createDateColumn<WebhookLog>('created_at', 'Time'),
    helper.display({
        id: 'source',
        header: 'Source',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => (
            <span className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset ${sourceColor[ctx.row.original.source] ?? ''}`}>
                {ctx.row.original.source}
            </span>
        ),
    }),
    helper.accessor('method', {
        id: 'method',
        header: 'Method',
        enableColumnFilter: false,
        enableHiding: false,
    }),
    helper.display({
        id: 'status_code',
        header: 'Status',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => {
            const code = ctx.row.original.status_code;
            return (
                <span className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset ${statusColor(code)}`}>
                    {code ?? 'processing'}
                </span>
            );
        },
    }),
    helper.accessor('ip_address', {
        id: 'ip_address',
        header: 'IP',
        enableColumnFilter: false,
        enableHiding: false,
    }),
    helper.display({
        id: 'headers',
        header: 'Headers',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => <JsonViewer data={ctx.row.original.headers} label="headers" />,
    }),
    helper.display({
        id: 'payload',
        header: 'Payload',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => <JsonViewer data={ctx.row.original.payload} label="payload" />,
    }),
    helper.display({
        id: 'response_body',
        header: 'Response',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => <JsonViewer data={ctx.row.original.response_body} label="response" />,
    }),
];

export default function WebhookLogIndex() {
    const load = useCallback(async (params: Record<string, any>) => {
        const res = await fetch(fetchRoute({ query: params }).url);
        return res.json();
    }, []);

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-col">
                <h1 className="text-xl font-semibold">Webhook Logs</h1>
                <p className="hidden text-sm text-gray-500 sm:block">Raw incoming webhook requests from Lodgify and WhatsApp</p>
            </div>
            <NextTable<WebhookLog>
                load={load}
                id={'id' as keyof WebhookLog}
                columns={columns}
                mode="table"
            />
        </div>
    );
}

WebhookLogIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
