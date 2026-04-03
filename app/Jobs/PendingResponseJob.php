<?php

namespace App\Jobs;

use App\Models\SystemLog;
use App\Models\WhatsappMessage;
use App\Service\WhatsApp\TwoChatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PendingResponseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('agents');
    }

    public function handle(TwoChatService $twoChat): void
    {
        $pendingMessages = WhatsappMessage::where('direction', 'outbound')
            ->whereNull('sent_at')
            ->whereNotNull('pending_send_at')
            ->where('pending_send_at', '<=', now())
            ->get();

        if ($pendingMessages->isEmpty()) {
            return;
        }

        $sent = 0;
        $failed = 0;

        foreach ($pendingMessages as $message) {
            try {
                $result = $twoChat->sendMessage($message->phone_number, $message->message_body);

                $message->update([
                    'twochat_message_id' => $result['message_uuid'] ?? null,
                    'sent_at' => now(),
                    'pending_send_at' => null,
                ]);

                $sent++;
            } catch (\Throwable $e) {
                Log::error('Pending message send failed', [
                    'message_id' => $message->id,
                    'phone' => $message->phone_number,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        SystemLog::create([
            'agent' => 'pending_response',
            'action' => 'batch_sent',
            'status' => $failed > 0 ? 'partial' : 'success',
            'payload' => [
                'total' => $pendingMessages->count(),
                'sent' => $sent,
                'failed' => $failed,
            ],
        ]);
    }
}
