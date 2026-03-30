import { createColumnHelper, type ColumnDef } from '@tanstack/react-table';

import IndexPage from '@/components/index-page';
import AppLayout from '@/layouts/app-layout';
import { createDateColumn } from '@/lib/column-helpers';
import {
    create,
    destroy as destroyRoute,
    destroyBulk,
    fetch as fetchRoute,
    show,
} from '@/routes/backoffice/concierge/transaction';
import type { Transaction } from '@/types/transaction';

const helper = createColumnHelper<Transaction>();

const columns: ColumnDef<Transaction, any>[] = [
    createDateColumn<Transaction>('transaction_date', 'Date'),
    helper.display({
        id: 'type',
        header: 'Type',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => {
            const type = ctx.row.original.type;
            const color = type === 'income'
                ? 'bg-green-50 text-green-700 ring-green-700/10'
                : 'bg-red-50 text-red-700 ring-red-700/10';
            return (
                <span className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset ${color}`}>
                    {type}
                </span>
            );
        },
    }),
    helper.accessor('category', {
        id: 'category',
        header: 'Category',
        enableColumnFilter: false,
        enableHiding: false,
    }),
    helper.accessor('description', {
        id: 'description',
        header: 'Description',
        enableColumnFilter: false,
        enableHiding: false,
    }),
    helper.display({
        id: 'amount',
        header: 'Amount',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => `${ctx.row.original.amount} ${ctx.row.original.currency}`,
    }),
    helper.accessor('recorded_by', {
        id: 'recorded_by',
        header: 'Recorded By',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => ctx.getValue() || '-',
    }),
];

const routes = { fetch: fetchRoute, destroy: destroyRoute, destroyBulk, show, create };

export default function TransactionIndex() {
    return (
        <IndexPage<Transaction>
            title="Transactions"
            description="Financial ledger"
            addLabel="Add Transaction"
            columns={columns}
            routes={routes}
        />
    );
}

TransactionIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
