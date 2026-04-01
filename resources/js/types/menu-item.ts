import type { Model } from './model';

export type MenuItem = Model & {
    name: string;
    name_fr: string | null;
    category: string;
    description: string | null;
    price: string | null;
    currency: string;
    is_available: boolean;
    availability_note: string | null;
};
