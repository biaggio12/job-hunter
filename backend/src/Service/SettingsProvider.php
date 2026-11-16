<?php

namespace App\Service;

use App\Entity\Settings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Dostęp do ustawień (singleton w bazie). Dla kanałów powiadomień wartości z bazy
 * mają pierwszeństwo, a puste pola są uzupełniane zmiennymi środowiskowymi.
 */
class SettingsProvider
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%env(DISCORD_WEBHOOK_URL)%')]
        private readonly string $envDiscordWebhook,
        #[Autowire('%env(TELEGRAM_BOT_TOKEN)%')]
        private readonly string $envTelegramToken,
        #[Autowire('%env(TELEGRAM_CHAT_ID)%')]
        private readonly string $envTelegramChatId,
        #[Autowire('%env(APP_TIMEZONE)%')]
        private readonly string $timezone,
    ) {
    }

    public function get(): Settings
    {
        $settings = $this->em->find(Settings::class, Settings::ID);
        if (null === $settings) {
            $settings = new Settings();
            $this->em->persist($settings);
            $this->em->flush();
        }

        return $settings;
    }

    public function discordWebhookUrl(): ?string
    {
        return self::firstNonEmpty($this->get()->discordWebhookUrl, $this->envDiscordWebhook);
    }

    public function telegramBotToken(): ?string
    {
        return self::firstNonEmpty($this->get()->telegramBotToken, $this->envTelegramToken);
    }

    public function telegramChatId(): ?string
    {
        return self::firstNonEmpty($this->get()->telegramChatId, $this->envTelegramChatId);
    }

    /** Czy pole jest ustawione przez zmienną środowiskową (do podpowiedzi w UI). */
    public function envProvides(string $field): bool
    {
        return match ($field) {
            'discordWebhookUrl' => '' !== $this->envDiscordWebhook,
            'telegramBotToken' => '' !== $this->envTelegramToken,
            'telegramChatId' => '' !== $this->envTelegramChatId,
            default => false,
        };
    }

    public function timezone(): \DateTimeZone
    {
        return new \DateTimeZone($this->timezone ?: 'Europe/Warsaw');
    }

    /** Godziny ciszy (US-E4) — obsługuje zakres przez północ (np. 22:00–07:00). */
    public function isQuietTime(?\DateTimeImmutable $now = null): bool
    {
        $s = $this->get();
        if (null === $s->quietHoursStart || null === $s->quietHoursEnd || $s->quietHoursStart === $s->quietHoursEnd) {
            return false;
        }
        $now = ($now ?? new \DateTimeImmutable())->setTimezone($this->timezone());
        $current = $now->format('H:i');

        return $s->quietHoursStart < $s->quietHoursEnd
            ? $current >= $s->quietHoursStart && $current < $s->quietHoursEnd
            : $current >= $s->quietHoursStart || $current < $s->quietHoursEnd;
    }

    private static function firstNonEmpty(?string ...$values): ?string
    {
        foreach ($values as $v) {
            if (null !== $v && '' !== trim($v)) {
                return trim($v);
            }
        }

        return null;
    }
}
