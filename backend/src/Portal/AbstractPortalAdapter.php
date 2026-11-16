<?php

namespace App\Portal;

use App\Entity\SearchProfile;

abstract class AbstractPortalAdapter implements PortalAdapterInterface
{
    public function __construct(protected readonly PoliteHttpClient $http)
    {
    }

    /** Słowa kluczowe profilu; brak = jedno zapytanie bez słowa kluczowego. */
    protected function keywords(SearchProfile $profile): array
    {
        return [] === $profile->getKeywords() ? [''] : $profile->getKeywords();
    }

    /** Ujednolicenie poziomu doświadczenia do junior|mid|senior|lead. */
    protected static function seniority(?string $raw): ?string
    {
        if (null === $raw || '' === $raw) {
            return null;
        }
        $r = mb_strtolower($raw);

        return match (true) {
            str_contains($r, 'intern'), str_contains($r, 'trainee'), str_contains($r, 'staż'), str_contains($r, 'praktyk'),
            str_contains($r, 'junior'), str_contains($r, 'młodszy') => 'junior',
            str_contains($r, 'mid'), str_contains($r, 'medium'), str_contains($r, 'regular'), 'specjalista' === $r => 'mid',
            str_contains($r, 'senior'), str_contains($r, 'starszy'), str_contains($r, 'expert'), str_contains($r, 'ekspert') => 'senior',
            str_contains($r, 'lead'), str_contains($r, 'manager'), str_contains($r, 'kierownik'), str_contains($r, 'director'),
            str_contains($r, 'head'), str_contains($r, 'architect') => 'lead',
            default => null,
        };
    }

    /** Ujednolicenie typu umowy do b2b|uop|uz|other. */
    protected static function contract(?string $raw): ?string
    {
        if (null === $raw || '' === $raw) {
            return null;
        }
        $r = mb_strtolower($raw);

        return match (true) {
            str_contains($r, 'b2b') => 'b2b',
            str_contains($r, 'permanent'), str_contains($r, 'employment'), str_contains($r, 'uop'),
            str_contains($r, 'o pracę'), str_contains($r, 'full_time'), str_contains($r, 'full-time') => 'uop',
            str_contains($r, 'zlecen'), str_contains($r, 'mandate') => 'uz',
            default => 'other',
        };
    }

    /** Przeliczenie kwoty na miesięczną. */
    protected static function monthly(?float $amount, ?string $period): ?int
    {
        if (null === $amount || $amount <= 0) {
            return null;
        }
        $p = mb_strtolower((string) $period);

        return (int) round(match (true) {
            str_starts_with($p, 'h'), str_contains($p, 'godz') => $amount * 168,
            str_starts_with($p, 'd'), str_contains($p, 'dzie') => $amount * 21,
            str_starts_with($p, 'y'), str_contains($p, 'rok') => $amount / 12,
            default => $amount,
        });
    }

    protected static function date(mixed $value): ?\DateTimeImmutable
    {
        try {
            return match (true) {
                \is_int($value) || \is_float($value) => (new \DateTimeImmutable())->setTimestamp((int) ($value > 10_000_000_000 ? $value / 1000 : $value)),
                \is_string($value) && '' !== $value => new \DateTimeImmutable($value),
                default => null,
            };
        } catch (\Exception) {
            return null;
        }
    }

    /** Odczyt danych Next.js (__NEXT_DATA__) z HTML. */
    protected function nextData(string $html): array
    {
        // strpos zamiast regexu — strony mają setki KB i regex mógłby przekroczyć backtrack_limit.
        $start = strpos($html, 'id="__NEXT_DATA__"');
        $open = false === $start ? false : strpos($html, '>', $start);
        $close = false === $open ? false : strpos($html, '</script>', $open);
        if (false === $close) {
            throw PortalException::structureChanged($this->getName(), 'brak __NEXT_DATA__');
        }
        $data = json_decode(substr($html, $open + 1, $close - $open - 1), true);
        if (!\is_array($data)) {
            throw PortalException::structureChanged($this->getName(), 'niepoprawny __NEXT_DATA__');
        }

        return $data;
    }

    /** Odczyt zagnieżdżonej wartości ścieżką „a.b.c”. */
    protected static function path(array $data, string $path): mixed
    {
        foreach (explode('.', $path) as $key) {
            if (!\is_array($data) || !\array_key_exists($key, $data)) {
                return null;
            }
            $data = $data[$key];
        }

        return $data;
    }
}
