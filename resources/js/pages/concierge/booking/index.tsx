import { createColumnHelper, type ColumnDef } from '@tanstack/react-table';

import IndexPage from '@/components/index-page';
import AppLayout from '@/layouts/app-layout';
import { createDateColumn } from '@/lib/column-helpers';
import {
    fetch as fetchRoute,
    show,
} from '@/routes/backoffice/concierge/booking';
import type { Booking } from '@/types/booking';

const helper = createColumnHelper<Booking>();

const statusColor: Record<string, string> = {
    confirmed: 'bg-blue-50 text-blue-700 ring-blue-700/10',
    checked_in: 'bg-green-50 text-green-700 ring-green-700/10',
    checked_out: 'bg-gray-50 text-gray-600 ring-gray-600/10',
    cancelled: 'bg-red-50 text-red-700 ring-red-700/10',
};

const columns: ColumnDef<Booking, any>[] = [
    helper.accessor('guest_name', {
        id: 'guest_name',
        header: 'Guest',
        enableColumnFilter: false,
        enableHiding: false,
    }),
    helper.accessor('suite_name', {
        id: 'suite_name',
        header: 'Suite',
        enableColumnFilter: false,
        enableHiding: false,
    }),
    createDateColumn<Booking>('check_in', 'Check In'),
    createDateColumn<Booking>('check_out', 'Check Out'),
    helper.accessor('num_guests', {
        id: 'num_guests',
        header: 'Guests',
        enableColumnFilter: false,
        enableHiding: false,
    }),
    helper.display({
        id: 'booking_status',
        header: 'Status',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => {
            const status = ctx.row.original.booking_status;
            return (
                <span className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset ${statusColor[status] ?? ''}`}>
                    {status.replace('_', ' ')}
                </span>
            );
        },
    }),
    helper.accessor('booking_source', {
        id: 'booking_source',
        header: 'Source',
        enableColumnFilter: false,
        enableHiding: false,
    }),
    helper.display({
        id: 'total_amount',
        header: 'Total',
        enableColumnFilter: false,
        enableHiding: false,
        cell: (ctx) => `${ctx.row.original.total_amount} ${ctx.row.original.currency}`,
    }),
];

const routes = { fetch: fetchRoute, show };

export default function BookingIndex() {
    return (
        <IndexPage<Booking>
            title="Bookings"
            description="Guest bookings synced from Lodgify"
            columns={columns}
            routes={routes}
        />
    );
}

BookingIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
