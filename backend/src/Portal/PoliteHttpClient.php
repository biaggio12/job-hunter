<?php

namespace App\Portal;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * „Kulturalny” klient HTTP dla scraperów (US-F3):
 *  - minimalny odstęp między zapytaniami do tego samego hosta,
 *  - jawny User-Agent, timeout,
 *  - obsługa 429 (Retry-After) z jedną ponowną próbą,
 *  - rozpoznanie blokady anty-botowej (nie próbujemy jej obchodzić).
 */
class PoliteHttpClient
{
    /** @var array<string, float> host => znacznik czasu ostatniego zapytania */
    private array $lastRequestAt = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(int:SCRAPER_DELAY_MS)%')]
        private readonly int $delayMs,
        #[Autowire('%env(int:SCRAPER_MAX_PAGES)%')]
        private readonly int $maxPages,
        #[Autowire('%env(SCRAPER_USER_AGENT)%')]
        private readonly string $userAgent,
    ) {
    }

    public function maxPages(): int
    {
        return max(1, $this->maxPages);
    }

    /** @param array<string, mixed> $options */
    public function getJson(string $portal, string $url, array $options = []): array
    {
        return $this->json($portal, 'GET', $url, $options);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $options
     */
    public function postJson(string $portal, string $url, array $body, array $options = []): array
    {
        return $this->json($portal, 'POST', $url, $options + ['json' => $body]);
    }

    /** @param array<string, mixed> $options */
    public function getHtml(string $portal, string $url, array $options = []): string
    {
        $options['headers'] = ($options['headers'] ?? []) + [
            'Accept' => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'pl-PL,pl;q=0.9,en;q=0.8',
        ];

        return $this->request($portal, 'GET', $url, $options);
    }

    /** @param array<string, mixed> $options */
    private function json(string $portal, string $method, string $url, array $options): array
    {
        $options['headers'] = ($options['headers'] ?? []) + ['Accept' => 'application/json'];
        $content = $this->request($portal, $method, $url, $options);
        try {
            $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw PortalException::structureChanged($portal, 'odpowiedź nie jest poprawnym JSON-em');
        }
        if (!\is_array($data)) {
            throw PortalException::structureChanged($portal, 'nieoczekiwany format JSON');
        }

        return $data;
    }

    /** @param array<string, mixed> $options */
    private function request(string $portal, string $method, string $url, array $options, bool $retry = true): string
    {
        $this->throttle($url);
        $options['headers'] = ($options['headers'] ?? []) + ['User-Agent' => $this->userAgent];
        $options['timeout'] ??= 30;
        $options['max_redirects'] ??= 3;

        $this->logger->info('[{portal}] {method} {url}', ['portal' => $portal, 'method' => $method, 'url' => $url]);
        $response = $this->httpClient->request($method, $url, $options);
        $status = $response->getStatusCode();

        if (429 === $status && $retry) {
            $wait = (int) ($response->getHeaders(false)['retry-after'][0] ?? 30);
            $wait = min(max($wait, 5), 120);
            $this->logger->warning('[{portal}] 429 Too Many Requests — czekam {s}s', ['portal' => $portal, 's' => $wait]);
            sleep($wait);

            return $this->request($portal, $method, $url, $options, false);
        }

        $content = $response->getContent(false);
        if (\in_array($status, [403, 503], true) && $this->looksLikeBotChallenge($response->getHeaders(false), $content)) {
            throw PortalException::blocked($portal, $status);
        }
        if ($status >= 400) {
            throw new PortalException(\sprintf('%s: HTTP %d dla %s', $portal, $status, $url));
        }
        // Przekierowanie na stronę „zaktualizuj przeglądarkę” = portal odrzuca klientów niebędących przeglądarką.
        $finalUrl = (string) $response->getInfo('url');
        if (str_contains($finalUrl, 'aktualizacja-przegladarki') || str_contains($finalUrl, 'unsupported-browser')) {
            throw PortalException::blocked($portal, $status);
        }

        return $content;
    }

    /** @param array<string, list<string>> $headers */
    private function looksLikeBotChallenge(array $headers, string $content): bool
    {
        return isset($headers['cf-mitigated'])
            || str_contains($content, 'Just a moment...')
            || str_contains($content, 'challenges.cloudflare.com')
            || str_contains($content, 'captcha');
    }

    private function throttle(string $url): void
    {
        $host = (string) parse_url($url, \PHP_URL_HOST);
        $now = microtime(true);
        $last = $this->lastRequestAt[$host] ?? 0.0;
        $waitUs = (int) (($last + $this->delayMs / 1000 - $now) * 1_000_000);
        if ($waitUs > 0) {
            usleep($waitUs);
        }
        $this->lastRequestAt[$host] = microtime(true);
    }
}
