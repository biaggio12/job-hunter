<?php

namespace App\Notification;

use App\Entity\JobOffer;
use App\Service\SettingsProvider;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Telegram przez Bot API (US-E2). Wiadomości grupowane po 10 ogłoszeń (limit 4096 znaków). */
class TelegramChannel implements NotificationChannelInterface
{
    private const OFFERS_PER_MESSAGE = 10;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SettingsProvider $settings,
        private readonly OfferFormatter $formatter,
    ) {
    }

    public function getCode(): string { return 'telegram'; }
    public function getName(): string { return 'Telegram'; }

    public function isConfigured(): bool
    {
        return null !== $this->settings->telegramBotToken() && null !== $this->settings->telegramChatId();
    }

    public function isEnabled(): bool
    {
        return $this->settings->get()->telegramEnabled && $this->isConfigured();
    }

    public function sendOffers(array $offers): void
    {
        $chunks = array_chunk($offers, self::OFFERS_PER_MESSAGE);
        foreach ($chunks as $i => $chunk) {
            $parts = [];
            if (0 === $i) {
                $parts[] = '🆕 <b>'.$this->formatter->countNew(\count($offers)).'</b>';
            }
            foreach ($chunk as $o) {
                $parts[] = $this->format($o);
            }
            $this->send(mb_substr(implode("\n\n", $parts), 0, 4000));
            if ($i < \count($chunks) - 1) {
                usleep(1_100_000);
            }
        }
    }

    public function sendMessage(string $title, string $text): void
    {
        $this->send('<b>'.self::e($title)."</b>\n".self::e($text));
    }

    private function format(JobOffer $o): string
    {
        $line = '<a href="'.self::e($o->getUrl()).'"><b>'.self::e($o->getTitle()).'</b></a>'
            ."\n🏢 ".self::e($o->getCompany())
            ."\n💰 ".self::e($this->formatter->salary($o))
            ."\n📍 ".self::e($this->formatter->workMode($o).($o->getLocation() ? ' · '.$o->getLocation() : ''))
            ."\n🔎 ".self::e($o->getPortal()->getName());
        if ($appUrl = $this->formatter->appUrl($o)) {
            $line .= ' · <a href="'.self::e($appUrl).'">Job Hunter</a>';
        }

        return $line;
    }

    private function send(string $html): void
    {
        $token = $this->settings->telegramBotToken();
        $chatId = $this->settings->telegramChatId();
        if (null === $token || null === $chatId) {
            throw new NotificationException('Telegram: brak tokenu bota lub ID czatu.');
        }
        try {
            $response = $this->httpClient->request('POST', 'https://api.telegram.org/bot'.$token.'/sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => $html,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ],
                'timeout' => 20,
            ]);
            $status = $response->getStatusCode();
            if ($status >= 300) {
                $desc = $response->toArray(false)['description'] ?? 'nieznany błąd';
                throw new NotificationException(\sprintf('Telegram: HTTP %d — %s', $status, $desc));
            }
        } catch (\Symfony\Contracts\HttpClient\Exception\ExceptionInterface $e) {
            // Nie logujemy URL-a — zawiera token bota.
            throw new NotificationException('Telegram: błąd połączenia ('.$e::class.')', 0, $e);
        }
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
    }
}
