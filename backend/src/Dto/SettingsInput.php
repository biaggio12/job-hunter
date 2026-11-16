<?php

namespace App\Dto;

use App\Entity\Settings;
use Symfony\Component\Validator\Constraints as Assert;

final class SettingsInput
{
    /** @param list<string> $notifyKeywords */
    public function __construct(
        public readonly bool $discordEnabled = true,
        #[Assert\Regex(pattern: '#^https://(discord\.com|discordapp\.com|ptb\.discord\.com|canary\.discord\.com)/api/webhooks/\d+/[\w-]+$#', message: 'To nie wygląda na adres webhooka Discorda.')]
        public readonly ?string $discordWebhookUrl = null,
        public readonly bool $telegramEnabled = false,
        #[Assert\Regex(pattern: '/^\d+:[\w-]+$/', message: 'Nieprawidłowy format tokenu bota Telegrama.')]
        public readonly ?string $telegramBotToken = null,
        #[Assert\Regex(pattern: '/^-?\d+$|^@\w+$/', message: 'ID czatu to liczba (np. -100123…) lub @nazwa_kanału.')]
        public readonly ?string $telegramChatId = null,
        #[Assert\PositiveOrZero]
        public readonly ?int $notifyMinSalary = null,
        public readonly bool $notifyIncludeNoSalary = true,
        public readonly bool $notifyRemoteOnly = false,
        #[Assert\Count(max: 30)]
        #[Assert\All([new Assert\Type('string'), new Assert\Length(max: 60)])]
        public readonly array $notifyKeywords = [],
        #[Assert\Regex(pattern: '/^([01]\d|2[0-3]):[0-5]\d$/', message: 'Format godziny: HH:MM.')]
        public readonly ?string $quietHoursStart = null,
        #[Assert\Regex(pattern: '/^([01]\d|2[0-3]):[0-5]\d$/', message: 'Format godziny: HH:MM.')]
        public readonly ?string $quietHoursEnd = null,
        /**
         * Sekrety, które mają zostać usunięte. Puste pole sekretu bez wpisu tutaj zostawia poprzednią
         * wartość (UI nie dostaje sekretów w całości, więc nie może ich odesłać).
         *
         * @var list<string>
         */
        #[Assert\All([new Assert\Choice(choices: ['discordWebhookUrl', 'telegramBotToken'])])]
        public readonly array $clearSecrets = [],
    ) {
    }

    public function applyTo(Settings $s): void
    {
        $s->discordEnabled = $this->discordEnabled;
        $s->telegramEnabled = $this->telegramEnabled;
        foreach (['discordWebhookUrl', 'telegramBotToken'] as $field) {
            $value = null === $this->$field ? '' : trim($this->$field);
            if ('' !== $value) {
                $s->$field = $value;
            } elseif (\in_array($field, $this->clearSecrets, true)) {
                $s->$field = null;
            }
        }
        $s->telegramChatId = null === $this->telegramChatId || '' === trim($this->telegramChatId) ? null : trim($this->telegramChatId);
        $s->notifyMinSalary = $this->notifyMinSalary ?: null;
        $s->notifyIncludeNoSalary = $this->notifyIncludeNoSalary;
        $s->notifyRemoteOnly = $this->notifyRemoteOnly;
        $s->notifyKeywords = array_values(array_unique(array_filter(array_map('trim', $this->notifyKeywords))));
        $s->quietHoursStart = $this->quietHoursStart ?: null;
        $s->quietHoursEnd = $this->quietHoursEnd ?: null;
    }
}
