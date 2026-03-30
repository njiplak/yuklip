<?php

namespace App\Service\WhatsApp;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwoChatService
{
    protected string $baseUrl = 'https://api.p.2chat.io/open';

    protected function headers(): array
    {
        return [
            'X-User-API-Key' => config('whatsapp.twochat_api_key'),
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Send a WhatsApp message via 2Chat.
     *
     * Returns the 2Chat response payload including message_uuid.
     * Throws RequestException on HTTP failure — callers are responsible for logging.
     */
    public function sendMessage(string $to, string $text): array
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/whatsapp/send-message", [
                'from_number' => config('whatsapp.twochat_phone_number'),
                'to_number' => $to,
                'text' => $text,
            ]);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * Subscribe to a 2Chat webhook event.
     */
    public function subscribeWebhook(string $event, string $hookUrl): array
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/webhooks/subscribe/{$event}", [
                'hook_url' => $hookUrl,
                'on_number' => config('whatsapp.twochat_phone_number'),
            ]);

        return $response->json();
    }
}
