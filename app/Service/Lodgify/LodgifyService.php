<?php

namespace App\Service\Lodgify;

use Illuminate\Support\Facades\Http;

class LodgifyService
{
    protected function client()
    {
        return Http::baseUrl(config('lodgify.base_url'))
            ->withHeaders([
                'X-ApiKey' => config('lodgify.api_key'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/*+json',
            ]);
    }

    /**
     * Subscribe to a webhook event.
     *
     * Valid Lodgify v1 events:
     * - booking_new_any_status
     * - booking_change         (covers status transitions, including cancellation)
     * - rate_change
     * - availability_change
     * - guest_message_received
     * - booking_payment_received
     * - booking_payment_refunded
     * - booking_payment_deleted
     *
     * Returns: { "id": "string", "secret": "string" }
     * The secret is only returned once at creation. Store it.
     */
    public function subscribeWebhook(string $event, string $targetUrl): array
    {
        $response = $this->client()
            ->post('/webhooks/v1/subscribe', [
                'event' => $event,
                'target_url' => $targetUrl,
            ])
            ->throw();

        $data = $response->json();

        return is_array($data) ? $data : ['raw' => $data];
    }

    public function unsubscribeWebhook(string $webhookId): array
    {
        $response = $this->client()
            ->delete('/webhooks/v1/unsubscribe', ['id' => $webhookId])
            ->throw();

        $data = $response->json();

        return is_array($data) ? $data : ['raw' => $data];
    }

    public function listWebhooks(): array
    {
        return $this->client()
            ->get('/webhooks/v1/list')
            ->throw()
            ->json();
    }
}
