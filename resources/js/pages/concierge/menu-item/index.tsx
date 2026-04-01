import { createColumnHelper, type ColumnDef } from '@tanstack/react-table';

import IndexPage from '@/components/index-page';
import AppLayout from '@/layouts/app-layout';
import {
    create,
    destroy as destroyRoute,
    destroyBulk,
    fetch as fetchRoute,
    show,
} from '@/routes/backoffice/concierge/menu-item';
import type { MenuItem } from '@/types/menu-item';

const helper = createColumnHelper<MenuItem>();

const categoryColor: Record<string, string> = {
    breakfast: 'bg-amber-50 text-amber-700 ring-amber-700/10',
    lunch: 'bg-green-50 text-green-700 ring-green-700/10',
    dinner: 'bg-indigo-50 text-indigo-700 ring-indigo-700/10',
    drinks: 'bg-sky-50 text-sky-700 ring-sky-700/10',
    snacks: 'bg-rose-50 text-rose-700 ring-rose-700/10',
};

const columns: ColumnDef<MenuItem, any>[] = [
    helper.accessor('name', {
        id: 'name',
        header: 'Name',
        enableColumnFilter: false,
        enableHiding: false,
    }),
    helper.display({
        id: 'category',
        header: 'Category',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => {
            const cat = ctx.row.original.category;
            return (
                <span className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset ${categoryColor[cat] ?? ''}`}>
                    {cat}
                </span>
            );
        },
    }),
    helper.display({
        id: 'price',
        header: 'Price',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => {
            const { price, currency } = ctx.row.original;
            return price ? `${price} ${currency}` : 'Included';
        },
    }),
    helper.display({
        id: 'is_available',
        header: 'Available',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => (
            <span className={`inline-flex h-2 w-2 rounded-full ${ctx.row.original.is_available ? 'bg-green-500' : 'bg-gray-300'}`} />
        ),
    }),
    helper.accessor('availability_note', {
        id: 'availability_note',
        header: 'Note',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => ctx.getValue() ?? '-',
    }),
];

const routes = { fetch: fetchRoute, destroy: destroyRoute, destroyBulk, show, create };

export default function MenuItemIndex() {
    return (
        <IndexPage<MenuItem>
            title="Menu Items"
            description="Manage food, drinks, and services available to guests"
            addLabel="Add Item"
            columns={columns}
            routes={routes}
        />
    );
}

MenuItemIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
