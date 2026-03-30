import { createColumnHelper, type ColumnDef } from '@tanstack/react-table';

import IndexPage from '@/components/index-page';
import AppLayout from '@/layouts/app-layout';
import {
    create,
    destroy as destroyRoute,
    destroyBulk,
    fetch as fetchRoute,
    show,
} from '@/routes/backoffice/concierge/offer';
import type { Offer } from '@/types/offer';

const helper = createColumnHelper<Offer>();

const categoryColor: Record<string, string> = {
    wellness: 'bg-purple-50 text-purple-700 ring-purple-700/10',
    dining: 'bg-orange-50 text-orange-700 ring-orange-700/10',
    experience: 'bg-sky-50 text-sky-700 ring-sky-700/10',
    transport: 'bg-emerald-50 text-emerald-700 ring-emerald-700/10',
};

const columns: ColumnDef<Offer, any>[] = [
    helper.accessor('offer_code', {
        id: 'offer_code',
        header: 'Code',
        enableColumnFilter: false,
        enableHiding: false,
    }),
    helper.accessor('title', {
        id: 'title',
        header: 'Title',
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
    helper.accessor('timing_rule', {
        id: 'timing_rule',
        header: 'Timing',
        enableColumnFilter: false,
        enableHiding: false,
    }),
    helper.display({
        id: 'price',
        header: 'Price',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => {
            const { price, currency } = ctx.row.original;
            return price ? `${price} ${currency}` : '-';
        },
    }),
    helper.display({
        id: 'is_active',
        header: 'Active',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => (
            <span className={`inline-flex h-2 w-2 rounded-full ${ctx.row.original.is_active ? 'bg-green-500' : 'bg-gray-300'}`} />
        ),
    }),
];

const routes = { fetch: fetchRoute, destroy: destroyRoute, destroyBulk, show, create };

export default function OfferIndex() {
    return (
        <IndexPage<Offer>
            title="Offers"
            description="Manage upsell offer catalog"
            addLabel="Add Offer"
            columns={columns}
            routes={routes}
        />
    );
}

OfferIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
