<?php

namespace App\Command;

use App\Enum\ScanStatus;
use App\Enum\ScanTrigger;
use App\Message\ScanPortalMessage;
use App\Notification\NotificationDispatcher;
use App\Portal\PortalAdapterRegistry;
use App\Service\ScanService;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/** Ręczny skan (US-B2): `bin/console app:scan [portal] [--async]`. */
#[AsCommand(name: 'app:scan', description: 'Skanuje wszystkie włączone portale lub wskazany portal')]
final class ScanCommand
{
    public function __construct(
        private readonly ScanService $scanService,
        private readonly NotificationDispatcher $notifications,
        private readonly PortalAdapterRegistry $registry,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Kod portalu (np. justjoin, nofluffjobs, bulldogjob, theprotocol, pracuj)')] ?string $portal = null,
        #[Option(description: 'Zakolejkuj skan dla workera zamiast uruchamiać go od razu')] bool $async = false,
        #[Option(description: 'Nie wysyłaj powiadomień po skanie')] bool $noNotify = false,
    ): int {
        if (null !== $portal && !$this->registry->has($portal)) {
            $io->error(\sprintf('Nieznany portal "%s". Dostępne: %s', $portal, implode(', ', array_keys($this->registry->all()))));

            return Command::INVALID;
        }

        $runs = $this->scanService->createRuns($portal, $async ? ScanTrigger::Manual : ScanTrigger::Console);
        if ([] === $runs) {
            $io->warning('Brak włączonych portali do skanowania.');

            return Command::SUCCESS;
        }

        if ($async) {
            foreach ($runs as $run) {
                $this->bus->dispatch(new ScanPortalMessage((int) $run->getId()));
            }
            $io->success(\sprintf('Zakolejkowano %d skan(ów) — wykona je worker.', \count($runs)));

            return Command::SUCCESS;
        }

        $rows = [];
        $failed = 0;
        foreach ($runs as $run) {
            $io->writeln(\sprintf('Skanuję <info>%s</info>…', $run->getPortal()->getName()));
            $run = $this->scanService->run((int) $run->getId());
            $ok = ScanStatus::Success === $run?->getStatus();
            $failed += $ok ? 0 : 1;
            $rows[] = [
                $run?->getPortal()->getName(),
                $ok ? '<info>OK</info>' : '<error>BŁĄD</error>',
                $run?->getFoundCount(),
                $run?->getNewCount(),
                mb_strimwidth((string) $run?->getError(), 0, 90, '…'),
            ];
        }
        $io->table(['Portal', 'Status', 'Znalezione', 'Nowe', 'Uwagi'], $rows);

        if (!$noNotify) {
            $sent = $this->notifications->flushPending();
            $io->writeln(\sprintf('Wysłano powiadomień o %d ogłoszeniach.', $sent));
        }

        return $failed === \count($runs) ? Command::FAILURE : Command::SUCCESS;
    }
}
