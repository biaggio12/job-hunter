<?php

namespace App\Notification;

use App\Entity\JobOffer;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/** Wymienny kanał powiadomień (US-E2). Nowy kanał = nowa klasa implementująca ten interfejs. */
#[AutoconfigureTag(self::TAG)]
interface NotificationChannelInterface
{
    public const TAG = 'app.notification_channel';

    public function getCode(): string;

    public function getName(): string;

    /** Kanał ma komplet danych konfiguracyjnych. */
    public function isConfigured(): bool;

    /** Kanał jest skonfigurowany i włączony w ustawieniach. */
    public function isEnabled(): bool;

    /**
     * Wysyła powiadomienie o nowych ogłoszeniach (grupując je w miarę możliwości).
     *
     * @param list<JobOffer> $offers
     *
     * @throws NotificationException
     */
    public function sendOffers(array $offers): void;

    /** @throws NotificationException */
    public function sendMessage(string $title, string $text): void;
}
