<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\SystemLog;
use App\Service\Lodgify\LodgifyService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

/**
 * Backfill historical bookings from Lodgify.
 *
 * Webhooks only deliver bookings created/changed AFTER subscription, so any
 * stay that existed before that point is missing from our `bookings` table —
 * which means past-month occupancy is artificially low. This command pulls
 * Lodgify's reservation list for a date window and upserts into Booking using
 * the same field mapping as the webhook handler. It does NOT trigger welcome
 * messages, push notifications, customer linking, or revenue logging; those
 * are real-time side-effects, inappropriate to fire for historical rows.
 */
class LodgifyBackfillBookingsCommand extends Command
{
    protected $signature = 'lodgify:backfill-bookings
        {--months=12 : How many months back to backfill (used when --from is not provided)}
        {--from= : Explicit ISO date (YYYY-MM-DD) for the start of the stay window}
        {--to= : Explicit ISO date (YYYY-MM-DD) for the end of the stay window (defaults to today)}
        {--dry-run : Fetch and report counts without writing to the database}
        {--stay-filter=All : Lodgify stayFilter param (All|Upcoming|Current|Historic)}';

    protected $description = 'Pull historical bookings from Lodgify and upsert them into the local table';

    public function handle(LodgifyService $lodgify): int
    {
        $window = $this->resolveWindow();
        $stayFilter = (string) $this->option('stay-filter');
        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf(
            'Backfilling Lodgify bookings: %s → %s (stayFilter=%s)%s',
            $window['from']->toDateString(),
            $window['to']->toDateString(),
            $stayFilter,
            $dryRun ? ' [dry-run]' : '',
        ));

        try {
            $rows = $lodgify->listBookings([
                'stayFilter' => $stayFilter,
                'updatedSince' => null,
                // Lodgify's date filter on stays is implicit via stayFilter; we
                // additionally filter client-side by arrival date so the window
                // applies regardless of channel-specific quirks.
            ]);
        } catch (\Throwable $e) {
            $this->error('Lodgify request failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info(sprintf('Lodgify returned %d bookings; filtering to window…', count($rows)));

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $raw) {
            $arrival = $this->parseDate(Arr::get($raw, 'arrival') ?? Arr::get($raw, 'date_arrival'));
            $departure = $this->parseDate(Arr::get($raw, 'departure') ?? Arr::get($raw, 'date_departure'));

            if ($arrival === null || $departure === null) {
                $skipped++;
                continue;
            }
            if ($arrival->lt($window['from']) || $arrival->gt($window['to'])) {
                $skipped++;
                continue;
            }

            $payload = $this->mapToBookingFields($raw, $arrival, $departure);
            if ($payload === null) {
                $skipped++;
                continue;
            }
            $lodgifyId = $payload['lodgify_booking_id'];

            if ($dryRun) {
                $existing = Booking::where('lodgify_booking_id', $lodgifyId)->exists();
                $existing ? $updated++ : $created++;
                continue;
            }

            $booking = Booking::where('lodgify_booking_id', $lodgifyId)->first();

            if ($booking) {
                // Preserve manually-set statuses the way the webhook handler does.
                if (in_array($booking->booking_status, ['checked_in', 'checked_out'], true)) {
                    $payload['booking_status'] = $booking->booking_status;
                }
                $booking->update($payload);
                $updated++;
            } else {
                Booking::create($payload);
                $created++;
            }
        }

        if (!$dryRun) {
            SystemLog::create([
                'agent' => 'lodgify_backfill',
                'action' => 'backfill_completed',
                'status' => 'success',
                'payload' => [
                    'window_from' => $window['from']->toDateString(),
                    'window_to' => $window['to']->toDateString(),
                    'stay_filter' => $stayFilter,
                    'fetched' => count($rows),
                    'created' => $created,
                    'updated' => $updated,
                    'skipped' => $skipped,
                ],
            ]);
        }

        $this->table(
            ['Fetched', 'Created', 'Updated', 'Skipped (out-of-window/invalid)'],
            [[count($rows), $created, $updated, $skipped]],
        );

        return self::SUCCESS;
    }

    /**
     * @return array{from: Carbon, to: Carbon}
     */
    private function resolveWindow(): array
    {
        $from = $this->option('from')
            ? Carbon::parse((string) $this->option('from'))->startOfDay()
            : now()->subMonths((int) $this->option('months'))->startOfDay();

        $to = $this->option('to')
            ? Carbon::parse((string) $this->option('to'))->endOfDay()
            : now()->endOfDay();

        return ['from' => $from, 'to' => $to];
    }

    private function parseDate(mixed $raw): ?Carbon
    {
        if (!$raw) {
            return null;
        }
        try {
            return Carbon::parse((string) $raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Map a Lodgify booking row to local Booking fields. Returns null when the
     * row has no usable Lodgify id (we key on it).
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>|null
     */
    private function mapToBookingFields(array $raw, Carbon $arrival, Carbon $departure): ?array
    {
        $lodgifyId = (string) (Arr::get($raw, 'id') ?? Arr::get($raw, 'booking_id') ?? '');
        if ($lodgifyId === '') {
            return null;
        }

        $guest = Arr::get($raw, 'guest', []);
        $rooms = Arr::get($raw, 'rooms', Arr::get($raw, 'room_types', []));
        $firstRoom = is_array($rooms) ? (Arr::first($rooms) ?? []) : [];

        $suiteName = (string) (
            $firstRoom['name']
            ?? Arr::get($raw, 'property_name')
            ?? Arr::get($raw, 'source_text')
            ?? 'Unknown'
        );

        $nights = (int) (
            Arr::get($raw, 'nights')
            ?? max(1, (int) $arrival->diffInDays($departure))
        );

        $numGuests = (int) (
            Arr::get($raw, 'people')
            ?? collect($rooms ?: [])->sum('people')
            ?? 1
        );
        $numGuests = $numGuests > 0 ? $numGuests : 1;

        return [
            'lodgify_booking_id' => $lodgifyId,
            'guest_name' => (string) (Arr::get($guest, 'name') ?? 'Unknown'),
            'guest_phone' => (string) (Arr::get($guest, 'phone_number') ?? Arr::get($guest, 'phone') ?? ''),
            'guest_email' => Arr::get($guest, 'email'),
            'guest_nationality' => Arr::get($guest, 'country'),
            'num_guests' => $numGuests,
            'suite_name' => $suiteName,
            'check_in' => $arrival->toDateString(),
            'check_out' => $departure->toDateString(),
            'num_nights' => $nights,
            'booking_source' => (string) (
                Arr::get($raw, 'source_text')
                ?? Arr::get($raw, 'source')
                ?? 'Direct'
            ),
            'booking_status' => $this->mapStatus((string) Arr::get($raw, 'status', 'Booked')),
            'total_amount' => (float) (
                Arr::get($raw, 'total_amount')
                ?? Arr::get($raw, 'amount_total')
                ?? Arr::get($raw, 'price')
                ?? 0
            ),
            'currency' => (string) (
                Arr::get($raw, 'currency_code')
                ?? Arr::get($raw, 'currency')
                ?? 'MAD'
            ),
            'lodgify_synced_at' => now(),
        ];
    }

    /**
     * Mirrors WebhookController::mapLodgifyStatus so backfilled rows match the
     * statuses webhook-sourced rows would land on.
     */
    private function mapStatus(string $lodgifyStatus): string
    {
        return match (strtolower($lodgifyStatus)) {
            'booked', 'confirmed', 'open' => 'confirmed',
            'declined', 'cancelled' => 'cancelled',
            default => 'confirmed',
        };
    }
}
