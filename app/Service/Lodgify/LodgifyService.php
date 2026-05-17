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

    /**
     * GET /v2/properties — paginated.
     *
     * The endpoint returns either { data: [...], count } or a bare array
     * depending on Lodgify's variant; we normalize to a flat list of property
     * arrays. Pages of size 100 (Lodgify caps `size` at 100).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listProperties(int $pageSize = 100): array
    {
        $page = 1;
        $all = [];

        while (true) {
            $response = $this->client()
                ->get('/v2/properties', [
                    'page' => $page,
                    'size' => $pageSize,
                ])
                ->throw()
                ->json();

            $batch = $this->normalizePropertyBatch($response);

            if (empty($batch)) {
                break;
            }

            $all = array_merge($all, $batch);

            if (count($batch) < $pageSize) {
                break;
            }

            $page++;
            if ($page > 50) {
                // Safety stop — 5000 properties is well beyond expected scale.
                break;
            }
        }

        return $all;
    }

    /**
     * Count active (non-deleted, non-archived) properties — the real
     * denominator for occupancy.
     */
    public function countActiveProperties(): int
    {
        return collect($this->listProperties())
            ->reject(function ($p) {
                $status = strtolower((string) ($p['status'] ?? 'active'));
                return in_array($status, ['deleted', 'archived', 'inactive'], true);
            })
            ->count();
    }

    /**
     * GET /v2/properties/{id}/rooms — list room types for a property.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listPropertyRooms(int|string $propertyId): array
    {
        $response = $this->client()
            ->get("/v2/properties/{$propertyId}/rooms")
            ->throw()
            ->json();

        if (is_array($response) && array_is_list($response)) {
            return $response;
        }
        if (is_array($response) && isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }
        return [];
    }

    /**
     * GET /v2/availability/{propertyId} (optionally /{roomTypeId}) — returns
     * Lodgify's period-based availability for the window.
     *
     * Response shape (per MikeRobGIT/lodgify-mcp types):
     *   [{ user_id, property_id, room_type_id, periods: [
     *      { start, end, available (int units), closed_period, bookings: [...] }
     *   ] }]
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPropertyAvailability(
        int|string $propertyId,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        int|string|null $roomTypeId = null,
    ): array {
        $path = $roomTypeId === null
            ? "/v2/availability/{$propertyId}"
            : "/v2/availability/{$propertyId}/{$roomTypeId}";

        $response = $this->client()
            ->get($path, [
                'start' => $from->format('Y-m-d\T00:00:00\Z'),
                'end' => $to->format('Y-m-d\T23:59:59\Z'),
                'includeDetails' => 'true',
            ])
            ->throw()
            ->json();

        if (is_array($response) && array_is_list($response)) {
            return $response;
        }
        if (is_array($response) && isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }
        return is_array($response) ? [$response] : [];
    }

    /**
     * GET /v2/reservations/bookings — list bookings, used by the backfill
     * command. Lodgify caps `size` at 100 and returns { count, items } here.
     *
     * @param  array<string, mixed>  $extra extra query params (e.g. stayFilter, updatedSince)
     * @return array<int, array<string, mixed>>
     */
    public function listBookings(array $extra = [], int $pageSize = 100): array
    {
        $page = 1;
        $all = [];

        while (true) {
            $response = $this->client()
                ->get('/v2/reservations/bookings', array_merge([
                    'page' => $page,
                    'size' => $pageSize,
                    'includeCount' => 'true',
                ], $extra))
                ->throw()
                ->json();

            $items = is_array($response['items'] ?? null) ? $response['items'] : (
                is_array($response) && array_is_list($response) ? $response : []
            );

            if (empty($items)) {
                break;
            }

            $all = array_merge($all, $items);

            if (count($items) < $pageSize) {
                break;
            }

            $page++;
            if ($page > 200) {
                // Safety stop — 20k bookings is well past expected scale.
                break;
            }
        }

        return $all;
    }

    /**
     * Normalize the /v2/properties response into a flat property list.
     *
     * @param  mixed  $response
     * @return array<int, array<string, mixed>>
     */
    private function normalizePropertyBatch($response): array
    {
        if (is_array($response) && array_is_list($response)) {
            return $response;
        }
        if (is_array($response) && isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }
        if (is_array($response) && isset($response['items']) && is_array($response['items'])) {
            return $response['items'];
        }
        return [];
    }
}
