<?php

namespace App\Command;

use App\Service\PortalSynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Uruchamiana przy starcie kontenera: rejestruje portale z adapterów i zakłada dane startowe. */
#[AsCommand(name: 'app:bootstrap', description: 'Synchronizuje portale z adapterami i tworzy dane startowe')]
final class BootstrapCommand
{
    public function __construct(private readonly PortalSynchronizer $synchronizer)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $added = $this->synchronizer->sync();
        if ([] !== $added) {
            $io->writeln('Dodano portale: '.implode(', ', $added));
        }
        if ($this->synchronizer->seedDefaults()) {
            $io->writeln('Utworzono domyślny profil wyszukiwania.');
        }
        $io->success('Bootstrap zakończony.');

        return Command::SUCCESS;
    }
}
