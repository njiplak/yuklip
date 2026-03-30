import type { Model } from './model';
import type { Booking } from './booking';

export type SystemLog = Model & {
    agent: string;
    action: string;
    booking_id: number | null;
    payload: Record<string, unknown> | null;
    status: string;
    error_message: string | null;
    duration_ms: number | null;
    booking?: Booking;
};
