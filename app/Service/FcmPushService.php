<?php

namespace App\Service;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Throwable;

class FcmPushService
{
    /**
     * Fan out a notification to every registered device token.
     * Prunes tokens that the FCM API reports as permanently invalid so the
     * device_tokens table doesn't accumulate dead entries.
     */
    public static function broadcast(string $title, string $body, ?string $url = null, ?string $tag = null): void
    {
        if (!class_exists(Firebase::class)) {
            Log::warning('FCM broadcast skipped: kreait/laravel-firebase is not installed.');
            return;
        }

        $tokens = DeviceToken::query()->pluck('token')->all();

        if (empty($tokens)) {
            return;
        }

        $data = array_filter([
            'url' => $url,
            'tag' => $tag,
        ], fn ($v) => $v !== null && $v !== '');

        $message = CloudMessage::new()
            ->withNotification(FcmNotification::create($title, $body))
            ->withData($data);

        try {
            $messaging = Firebase::messaging();
            // Chunk to FCM's 500-token-per-request limit for sendMulticast.
            foreach (array_chunk($tokens, 500) as $batch) {
                $report = $messaging->sendMulticast($message, $batch);

                $invalidTokens = array_values(array_unique(array_merge(
                    method_exists($report, 'invalidTokens') ? $report->invalidTokens() : [],
                    method_exists($report, 'unknownTokens') ? $report->unknownTokens() : [],
                )));

                if (!empty($invalidTokens)) {
                    DeviceToken::whereIn('token', $invalidTokens)->delete();
                    Log::info('FCM pruned invalid device tokens', ['count' => count($invalidTokens)]);
                }
            }
        } catch (MessagingException $e) {
            Log::error('FCM broadcast failed', [
                'title' => $title,
                'error' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            Log::error('FCM broadcast crashed', [
                'title' => $title,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
