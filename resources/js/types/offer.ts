import type { Model } from './model';

export type Offer = Model & {
    offer_code: string;
    title: string;
    description: string;
    category: string;
    timing_rule: string;
    price: string | null;
    currency: string;
    is_active: boolean;
    max_sends_per_stay: number;
};
