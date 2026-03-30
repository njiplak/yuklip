import type { Model } from './model';

export type WebhookLog = Model & {
    source: string;
    method: string;
    url: string;
    headers: Record<string, unknown> | null;
    payload: Record<string, unknown> | null;
    status_code: number | null;
    response_body: Record<string, unknown> | null;
    ip_address: string | null;
};
