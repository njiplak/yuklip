<?php

namespace App\Console\Commands;

use App\Models\WebhookSubscription;
use App\Service\Lodgify\LodgifyService;
use App\Service\WhatsApp\TwoChatService;
use Illuminate\Console\Command;

class SetupWebhooksCommand extends Command
{
    protected $signature = 'concierge:setup-webhooks {--fresh : Unsubscribe all existing webhooks before registering}';

    protected $description = 'Register webhook subscriptions with Lodgify and 2Chat';

    /**
     * Lodgify events that the system handles.
     */
    /**
     * Lodgify v1 webhook events we subscribe to.
     *
     * `booking_change` is what fires on cancellation/decline — there is no
     * standalone `booking_cancelled` or `booking_deleted` event in Lodgify v1.
     * The handler routes by `booking.status` for cancellations.
     *
     * `booking_payment_*` events are NOT valid Lodgify v1 events — they return
     * HTTP 500 on subscribe. Do not add them back without confirmation.
     */
    protected array $lodgifyEvents = [
        'booking_new_any_status',
        'booking_change',
        'rate_change',
        'availability_change',
        'guest_message_received',
    ];

    public function handle(LodgifyService $lodgify, TwoChatService $twoChat): int
    {
        if (!$this->preflight()) {
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->unsubscribeAll($lodgify);
        }

        $baseUrl = config('app.url');
        $failed = false;

        // --- Lodgify webhooks ---
        // Each event gets a unique target URL via the `?event=...` query
        // string. Lodgify enforces uniqueness on the full callback URL, so
        // reusing a single URL across events triggers 409 "callback already
        // exists" after the first registration. Query strings don't affect
        // Laravel routing — all variants still hit /lodgify/webhook.
        //
        // Lodgify issues a distinct webhook secret PER SUBSCRIPTION (not per
        // account), so we persist each (event, subscription_id, secret) tuple
        // to the webhook_subscriptions table. The handler looks up the secret
        // by the `?event=...` query param on the incoming request.
        $this->info('Registering Lodgify webhooks...');

        foreach ($this->lodgifyEvents as $event) {
            $targetUrl = $baseUrl . '/lodgify/webhook?event=' . urlencode($event);
            try {
                $result = $lodgify->subscribeWebhook($event, $targetUrl);

                $subscriptionId = $result['id'] ?? null;
                $secret = $result['secret'] ?? null;

                if (!$secret) {
                    throw new \RuntimeException('subscribe response missing secret: ' . json_encode($result));
                }

                WebhookSubscription::updateOrCreate(
                    ['source' => 'lodgify', 'event' => $event],
                    [
                        'subscription_id' => $subscriptionId,
                        'secret' => $secret,
                        'target_url' => $targetUrl,
                    ],
                );

                $this->line("  {$event} => {$targetUrl} [OK, secret stored]");
            } catch (\Throwable $e) {
                $this->error("  {$event} => FAILED: {$e->getMessage()}");
                $failed = true;
            }
        }

        // --- 2Chat webhook ---
        $this->info('Registering 2Chat webhook...');
        $whatsappUrl = $baseUrl . '/whatsapp/webhook';
        try {
            $twoChat->subscribeWebhook('whatsapp.message.new', $whatsappUrl);
            $this->line("  whatsapp.message.new => {$whatsappUrl} [OK]");
        } catch (\Throwable $e) {
            $this->error("  whatsapp.message.new => FAILED: {$e->getMessage()}");
            $failed = true;
        }

        if ($failed) {
            $this->error('Some webhooks failed to register. Check errors above.');
            return self::FAILURE;
        }

        $this->info('All webhooks registered.');
        return self::SUCCESS;
    }

    /**
     * Unsubscribe only our own webhooks (target URL under APP_URL).
     * Lodgify's webhook list is account-scoped, so third-party integrations
     * such as PriceLabs are returned here too — we must not delete them.
     */
    protected function unsubscribeAll(LodgifyService $lodgify): void
    {
        $ownPrefix = rtrim((string) config('app.url'), '/');

        if ($ownPrefix === '') {
            $this->error('  Cannot run --fresh: APP_URL is not configured.');
            return;
        }

        $this->info("Unsubscribing existing Lodgify webhooks for {$ownPrefix} ...");

        try {
            $webhooks = $lodgify->listWebhooks();
        } catch (\Throwable $e) {
            $this->error("  Failed to list webhooks: {$e->getMessage()}");
            return;
        }

        if (empty($webhooks)) {
            $this->line('  No existing webhooks found.');
            return;
        }

        $unsubscribed = 0;

        foreach ($webhooks as $webhook) {
            $id = $webhook['id'] ?? null;
            $event = $webhook['event'] ?? 'unknown';
            $url = (string) ($webhook['url'] ?? '');

            if (!$id) {
                continue;
            }

            if (!str_starts_with($url, $ownPrefix)) {
                $this->line("  Skipping third-party: {$event} => {$url}");
                continue;
            }

            try {
                $lodgify->unsubscribeWebhook($id);
                WebhookSubscription::where('source', 'lodgify')
                    ->where('subscription_id', $id)
                    ->delete();
                $this->line("  Unsubscribed: {$event} ({$id}) [OK]");
                $unsubscribed++;
            } catch (\Throwable $e) {
                $this->error("  Unsubscribe {$event} ({$id}) => FAILED: {$e->getMessage()}");
            }
        }

        // Drop any rows whose subscription_id we couldn't unsubscribe
        // (orphans Lodgify has but we want to forget locally). Tolerate a
        // missing table so a first-run --fresh doesn't abort before the
        // subscribe loop has a chance to run.
        try {
            WebhookSubscription::where('source', 'lodgify')
                ->where('target_url', 'like', $ownPrefix . '%')
                ->delete();
        } catch (\Throwable $e) {
            $this->warn("  Local subscription cleanup skipped: {$e->getMessage()}");
        }

        if ($unsubscribed === 0) {
            $this->line('  Nothing to unsubscribe.');
        }

        $this->newLine();
    }

    protected function preflight(): bool
    {
        $ok = true;

        // Lodgify webhook secrets are issued per subscription at register time
        // and persisted into the webhook_subscriptions table, so no
        // LODGIFY_WEBHOOK_SECRET env var is required.
        $required = [
            'ANTHROPIC_API_KEY' => config('ai.providers.anthropic.key'),
            'LODGIFY_API_KEY' => config('lodgify.api_key'),
            'TWOCHAT_API_KEY' => config('whatsapp.twochat_api_key'),
            'TWOCHAT_PHONE_NUMBER' => config('whatsapp.twochat_phone_number'),
            'STAFF_WHATSAPP_NUMBER' => config('whatsapp.staff_phone_number'),
        ];

        $this->info('Checking environment...');

        foreach ($required as $name => $value) {
            if (empty($value)) {
                $this->error("  Missing: {$name}");
                $ok = false;
            } else {
                $this->line("  {$name} [set]");
            }
        }

        if (config('app.url') === 'http://localhost') {
            $this->warn('  APP_URL is still http://localhost — Lodgify and 2Chat cannot reach this.');
            $this->warn('  Set APP_URL to your public staging URL (e.g. https://staging.yourdomain.com)');
            $ok = false;
        }

        if (!$ok) {
            $this->error('Fix the issues above before registering webhooks.');
        }

        return $ok;
    }
}
