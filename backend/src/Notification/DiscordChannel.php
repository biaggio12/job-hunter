<?php

namespace App\Notification;

use App\Entity\JobOffer;
use App\Service\SettingsProvider;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Discord przez Webhook (US-E1). Ogłoszenia są grupowane po 10 embedów w jednej wiadomości
 * (limit Discorda), a odpowiedź 429 jest obsługiwana zgodnie z retry_after.
 */
class DiscordChannel implements NotificationChannelInterface
{
    private const EMBEDS_PER_MESSAGE = 10;
    private const COLOR = 0x6366F1;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SettingsProvider $settings,
        private readonly OfferFormatter $formatter,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getCode(): string { return 'discord'; }
    public function getName(): string { return 'Discord'; }

    public function isConfigured(): bool
    {
        return null !== $this->settings->discordWebhookUrl();
    }

    public function isEnabled(): bool
    {
        return $this->settings->get()->discordEnabled && $this->isConfigured();
    }

    public function sendOffers(array $offers): void
    {
        $chunks = array_chunk($offers, self::EMBEDS_PER_MESSAGE);
        foreach ($chunks as $i => $chunk) {
            $content = 0 === $i
                ? '🆕 **'.$this->formatter->countNew(\count($offers)).'** w Job Hunter'
                : null;
            $this->post(array_filter([
                'content' => $content,
                'embeds' => array_map($this->embed(...), $chunk),
            ]));
            if ($i < \count($chunks) - 1) {
                usleep(1_100_000); // ~1 wiadomość/s — bezpiecznie poniżej limitu webhooka
            }
        }
    }

    public function sendMessage(string $title, string $text): void
    {
        $this->post([
            'embeds' => [[
                'title' => mb_substr($title, 0, 256),
                'description' => mb_substr($text, 0, 4000),
                'color' => 0xF59E0B,
                'timestamp' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            ]],
        ]);
    }

    /** @return array<string, mixed> */
    private function embed(JobOffer $o): array
    {
        $lines = [
            '🏢 **'.$o->getCompany().'**',
            '💰 '.$this->formatter->salary($o),
            '📍 '.$this->formatter->workMode($o).($o->getLocation() ? ' · '.$o->getLocation() : ''),
        ];
        if ([] !== $o->getTags()) {
            $lines[] = '🏷️ '.implode(', ', \array_slice($o->getTags(), 0, 8));
        }
        if ($appUrl = $this->formatter->appUrl($o)) {
            $lines[] = '[Otwórz w Job Hunter]('.$appUrl.')';
        }

        return [
            'title' => mb_substr($o->getTitle(), 0, 256),
            'url' => $o->getUrl(),
            'description' => mb_substr(implode("\n", $lines), 0, 1000),
            'color' => self::COLOR,
            'footer' => ['text' => $o->getPortal()->getName()],
            'timestamp' => ($o->getPublishedAt() ?? $o->getFirstSeenAt())->format(\DATE_ATOM),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function post(array $payload, int $attempt = 1): void
    {
        $url = $this->settings->discordWebhookUrl();
        if (null === $url) {
            throw new NotificationException('Discord: brak adresu webhooka.');
        }
        $payload['username'] = 'Job Hunter';
        try {
            $response = $this->httpClient->request('POST', $url, ['json' => $payload, 'timeout' => 20]);
            $status = $response->getStatusCode();
            if (429 === $status && $attempt <= 3) {
                $retry = (float) ($response->toArray(false)['retry_after'] ?? 2);
                $this->logger->warning('Discord 429 — ponawiam za {s}s', ['s' => $retry]);
                usleep((int) (min($retry, 30) * 1_000_000) + 250_000);
                $this->post($payload, $attempt + 1);

                return;
            }
            if ($status >= 300) {
                throw new NotificationException(\sprintf('Discord: HTTP %d — %s', $status, mb_substr($response->getContent(false), 0, 300)));
            }
        } catch (\Symfony\Contracts\HttpClient\Exception\ExceptionInterface $e) {
            throw new NotificationException('Discord: '.$e->getMessage(), 0, $e);
        }
    }
}
