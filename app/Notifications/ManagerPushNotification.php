<?php

namespace App\Notifications;

use Illuminate\Notification\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ManagerPushNotification extends Notification
{
    public function __construct(
        private string $title,
        private string $body,
        private ?string $url = null,
        private ?string $tag = null,
    ) {}

    public function via(mixed $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(mixed $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->icon('/pwa-icon-192.png')
            ->badge('/pwa-icon-192.png')
            ->body($this->body)
            ->tag($this->tag ?? 'yasmine')
            ->data(['url' => $this->url ?? '/']);
    }
}
