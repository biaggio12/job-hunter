<?php

namespace App\Command;

use App\Notification\NotificationDispatcher;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:notify', description: 'Wysyła zaległe powiadomienia albo testową wiadomość (app:notify test discord)')]
final class NotifyCommand
{
    public function __construct(private readonly NotificationDispatcher $notifications)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: '"flush" (domyślnie) lub "test"')] string $action = 'flush',
        #[Argument(description: 'Kanał dla testu: discord|telegram')] ?string $channel = null,
    ): int {
        if ('test' === $action) {
            try {
                $this->notifications->sendTest($channel ?? 'discord');
            } catch (\Throwable $e) {
                $io->error($e->getMessage());

                return Command::FAILURE;
            }
            $io->success('Wysłano wiadomość testową.');

            return Command::SUCCESS;
        }
        $io->success(\sprintf('Powiadomiono o %d ogłoszeniach.', $this->notifications->flushPending()));

        return Command::SUCCESS;
    }
}
