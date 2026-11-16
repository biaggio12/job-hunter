<?php

namespace App\MessageHandler;

use App\Message\FlushNotificationsMessage;
use App\Notification\NotificationDispatcher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class FlushNotificationsHandler
{
    public function __construct(private readonly NotificationDispatcher $notifications)
    {
    }

    public function __invoke(FlushNotificationsMessage $message): void
    {
        $this->notifications->flushPending();
    }
}
