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
     * Available events:
     * - booking_new
     * - booking_change
     * - booking_cancelled
     * - booking_deleted
     * - availability_change
     * - rate_change
     * - guest_message_received
     * - booking_payment_received
     * - booking_payment_refunded
     * - booking_payment_deleted
     *
     * Returns: { "id": "string", "secret": "string" }
     * IMPORTANT: The secret is only returned once at creation. Store it.
     */
    public function subscribeWebhook(string $event, string $targetUrl): array
    {
        $response = $this->client()->post('/webhooks/v1/subscribe', [
            'event' => $event,
            'target_url' => $targetUrl,
        ]);

        $data = $response->json();

        return is_array($data) ? $data : ['raw' => $data];
    }

    /**
     * Unsubscribe from a webhook by its ID.
     */
    public function unsubscribeWebhook(string $webhookId): array
    {
        $response = $this->client()->delete('/webhooks/v1/unsubscribe', [
            'id' => $webhookId,
        ]);

        $data = $response->json();

        return is_array($data) ? $data : ['raw' => $data];
    }

    /**
     * List all subscribed webhooks.
     */
    public function listWebhooks(): array
    {
        $response = $this->client()->get('/webhooks/v1/list');

        return $response->json();
    }
}
