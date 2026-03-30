<?php

namespace App\Console\Commands;

use App\Service\Lodgify\LodgifyService;
use App\Service\WhatsApp\TwoChatService;
use Illuminate\Console\Command;

class SetupWebhooksCommand extends Command
{
    protected $signature = 'concierge:setup-webhooks';

    protected $description = 'Register webhook subscriptions with Lodgify and 2Chat';

    /**
     * Lodgify events that the system handles.
     */
    protected array $lodgifyEvents = [
        'booking_new',
        'booking_change',
        'booking_cancelled',
        'booking_deleted',
        'rate_change',
        'availability_change',
        'guest_message_received',
        'booking_payment_received',
        'booking_payment_refunded',
        'booking_payment_deleted',
    ];

    public function handle(LodgifyService $lodgify, TwoChatService $twoChat): int
    {
        if (!$this->preflight()) {
            return self::FAILURE;
        }

        $baseUrl = config('app.url');
        $failed = false;

        // --- Lodgify webhooks ---
        $this->info('Registering Lodgify webhooks...');
        foreach ($this->lodgifyEvents as $event) {
            $targetUrl = $baseUrl . '/lodgify/webhook';
            try {
                $result = $lodgify->subscribeWebhook($event, $targetUrl);

                if (isset($result['secret']) && $event === 'booking_new') {
                    $this->warn("  IMPORTANT: Lodgify returned a webhook secret. Save it as LODGIFY_WEBHOOK_SECRET in your .env:");
                    $this->line("  LODGIFY_WEBHOOK_SECRET={$result['secret']}");
                }

                $this->line("  {$event} => {$targetUrl} [OK]");
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

    protected function preflight(): bool
    {
        $ok = true;

        $required = [
            'ANTHROPIC_API_KEY' => config('ai.providers.anthropic.key'),
            'LODGIFY_API_KEY' => config('lodgify.api_key'),
            'LODGIFY_WEBHOOK_SECRET' => config('lodgify.webhook_secret'),
            'TWOCHAT_API_KEY' => config('whatsapp.twochat_api_key'),
            'TWOCHAT_PHONE_NUMBER' => config('whatsapp.twochat_phone_number'),
            'WHATSAPP_WEBHOOK_SECRET' => config('whatsapp.webhook_secret'),
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
