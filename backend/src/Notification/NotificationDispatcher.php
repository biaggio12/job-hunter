<?php

namespace App\Notification;

use App\Repository\JobOfferRepository;
use App\Service\SettingsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Wysyła oczekujące powiadomienia o nowych ogłoszeniach do wszystkich włączonych kanałów.
 * Każde ogłoszenie jest wysyłane dokładnie raz (flaga notificationPending → notifiedAt).
 * W godzinach ciszy nic nie wysyła — zaległe pójdą zbiorczo po ich zakończeniu (US-E4).
 */
class NotificationDispatcher
{
    /** Maks. liczba ogłoszeń w jednej turze; resztę wyśle kolejne wywołanie. */
    private const MAX_PER_FLUSH = 50;

    /** @var array<string, NotificationChannelInterface> */
    private array $channels = [];

    /** @param iterable<NotificationChannelInterface> $channels */
    public function __construct(
        #[AutowireIterator(NotificationChannelInterface::TAG)] iterable $channels,
        private readonly JobOfferRepository $offers,
        private readonly SettingsProvider $settings,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
        foreach ($channels as $channel) {
            $this->channels[$channel->getCode()] = $channel;
        }
    }

    /** @return array<string, NotificationChannelInterface> */
    public function channels(): array
    {
        return $this->channels;
    }

    /** @return int liczba ogłoszeń, o których powiadomiono */
    public function flushPending(): int
    {
        if ($this->settings->isQuietTime()) {
            return 0;
        }
        $pending = $this->offers->findPendingNotifications(self::MAX_PER_FLUSH);
        if ([] === $pending) {
            return 0;
        }
        $enabled = array_filter($this->channels, static fn ($c) => $c->isEnabled());
        $now = new \DateTimeImmutable();

        if ([] === $enabled) {
            // Brak skonfigurowanego kanału — nie kumulujemy zaległości, które zalałyby kanał po konfiguracji.
            $this->logger->notice('Brak włączonych kanałów powiadomień — pomijam {n} ogłoszeń.', ['n' => \count($pending)]);
            foreach ($pending as $offer) {
                $offer->markNotified($now);
            }
            $this->em->flush();

            return 0;
        }

        $delivered = false;
        foreach ($enabled as $channel) {
            try {
                $channel->sendOffers($pending);
                $delivered = true;
            } catch (NotificationException $e) {
                $this->logger->error('Powiadomienie {channel} nieudane: {error}', ['channel' => $channel->getName(), 'error' => $e->getMessage()]);
            }
        }
        if (!$delivered) {
            return 0; // zostają w kolejce — spróbujemy przy kolejnym wywołaniu
        }
        foreach ($pending as $offer) {
            $offer->markNotified($now);
        }
        $this->em->flush();

        return \count($pending);
    }

    /** Alert systemowy (np. awaria scrapera, US-F2) — do wszystkich włączonych kanałów, ignoruje godziny ciszy. */
    public function sendAlert(string $title, string $text): bool
    {
        $sent = false;
        foreach ($this->channels as $channel) {
            if (!$channel->isEnabled()) {
                continue;
            }
            try {
                $channel->sendMessage($title, $text);
                $sent = true;
            } catch (NotificationException $e) {
                $this->logger->error('Alert {channel} nieudany: {error}', ['channel' => $channel->getName(), 'error' => $e->getMessage()]);
            }
        }

        return $sent;
    }

    /** Testowa wiadomość (US-E5). */
    public function sendTest(string $code): void
    {
        $channel = $this->channels[$code] ?? throw new \InvalidArgumentException('Nieznany kanał: '.$code);
        if (!$channel->isConfigured()) {
            throw new NotificationException($channel->getName().': kanał nie jest skonfigurowany.');
        }
        $channel->sendMessage('✅ Test powiadomienia', 'Kanał '.$channel->getName().' jest poprawnie skonfigurowany w Job Hunter.');
    }
}
