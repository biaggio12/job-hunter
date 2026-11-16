<?php

namespace App\MessageHandler;

use App\Message\ScanPortalMessage;
use App\Notification\NotificationDispatcher;
use App\Service\ScanService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ScanPortalHandler
{
    public function __construct(
        private readonly ScanService $scanService,
        private readonly NotificationDispatcher $notifications,
    ) {
    }

    public function __invoke(ScanPortalMessage $message): void
    {
        $this->scanService->run($message->scanRunId);
        $this->notifications->flushPending();
    }
}
