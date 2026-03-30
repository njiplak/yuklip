import type { Model } from './model';
import type { Booking } from './booking';

export type Transaction = Model & {
    booking_id: number | null;
    type: string;
    category: string;
    description: string;
    amount: string;
    currency: string;
    transaction_date: string;
    payment_method: string | null;
    reference: string | null;
    recorded_by: string | null;
    booking?: Booking;
};
