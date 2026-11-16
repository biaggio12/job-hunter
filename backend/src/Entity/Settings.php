<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ustawienia aplikacji (singleton, id = 1).
 * Puste pola kanałów oznaczają „użyj wartości ze zmiennej środowiskowej”.
 */
#[ORM\Entity]
class Settings
{
    public const ID = 1;

    #[ORM\Id]
    #[ORM\Column]
    private int $id = self::ID;

    // --- Kanały powiadomień (US-E1, US-E2) ---
    #[ORM\Column]
    public bool $discordEnabled = true;

    #[ORM\Column(length: 500, nullable: true)]
    public ?string $discordWebhookUrl = null;

    #[ORM\Column]
    public bool $telegramEnabled = false;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $telegramBotToken = null;

    #[ORM\Column(length: 100, nullable: true)]
    public ?string $telegramChatId = null;

    // --- Reguły powiadomień (US-E3) ---
    /** Minimalne widełki „do” (lub „od”, gdy brak „do”), miesięcznie, w PLN. */
    #[ORM\Column(nullable: true)]
    public ?int $notifyMinSalary = null;

    /** Czy powiadamiać o ogłoszeniach bez podanych widełek, gdy ustawiono próg wynagrodzenia. */
    #[ORM\Column]
    public bool $notifyIncludeNoSalary = true;

    #[ORM\Column]
    public bool $notifyRemoteOnly = false;

    /** @var list<string> co najmniej jedno słowo musi wystąpić w tytule lub tagach; pusta = wszystkie */
    #[ORM\Column(type: Types::JSON)]
    public array $notifyKeywords = [];

    // --- Godziny ciszy (US-E4), format HH:MM; null = wyłączone ---
    #[ORM\Column(length: 5, nullable: true)]
    public ?string $quietHoursStart = null;

    #[ORM\Column(length: 5, nullable: true)]
    public ?string $quietHoursEnd = null;

    public function getId(): int { return $this->id; }
}
