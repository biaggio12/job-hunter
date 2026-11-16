<?php

namespace App;

use App\Message\FlushNotificationsMessage;
use App\Message\ScanAllPortalsMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Harmonogram uruchamiany przez worker (messenger:consume scheduler_default).
 * Zmiana SCAN_INTERVAL_HOURS wymaga restartu kontenera workera.
 */
#[AsSchedule]
final class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        #[Autowire(service: 'scheduler.cache')]
        private readonly CacheInterface $cache,
        #[Autowire('%env(int:SCAN_INTERVAL_HOURS)%')]
        private readonly int $scanIntervalHours,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        $hours = max(1, $this->scanIntervalHours);

        return (new SymfonySchedule())
            ->stateful($this->cache)            // po restarcie workera nadrabia pominięty skan
            ->processOnlyLastMissedRun(true)    // ...ale tylko jeden
            ->add(RecurringMessage::every(\sprintf('%d hours', $hours), new ScanAllPortalsMessage()))
            ->add(RecurringMessage::every('10 minutes', new FlushNotificationsMessage()));
    }
}
