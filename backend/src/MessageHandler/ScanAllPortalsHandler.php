<?php

namespace App\MessageHandler;

use App\Enum\ScanTrigger;
use App\Message\ScanAllPortalsMessage;
use App\Message\ScanPortalMessage;
use App\Service\ScanService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class ScanAllPortalsHandler
{
    public function __construct(
        private readonly ScanService $scanService,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(ScanAllPortalsMessage $message): void
    {
        foreach ($this->scanService->createRuns(null, ScanTrigger::Scheduled) as $run) {
            $this->bus->dispatch(new ScanPortalMessage((int) $run->getId()));
        }
    }
}
