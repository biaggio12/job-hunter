<?php

namespace App\Portal\Adapter;

use App\Entity\SearchProfile;
use App\Portal\AbstractPortalAdapter;
use App\Portal\PortalException;
use App\Portal\ScrapedOffer;
use App\Portal\SalaryText;

/**
 * theprotocol.it — lista ofert z danych Next.js.
 *
 * Uwaga: portal stoi za ochroną anty-botową Cloudflare. Gdy ją aktywuje, adapter zgłasza
 * czytelny błąd (widoczny w historii skanów i w alercie) zamiast próbować ją obejść.
 * Mapowanie pól jest defensywne, bo struktura danych nie jest publicznie udokumentowana.
 */
final class TheProtocolAdapter extends AbstractPortalAdapter
{
    private const BASE = 'https://theprotocol.it';

    public function getCode(): string { return 'theprotocol'; }
    public function getName(): string { return 'theprotocol.it'; }
    public function getHomepage(): string { return self::BASE; }

    public function fetch(SearchProfile $profile): iterable
    {
        foreach ($this->keywords($profile) as $keyword) {
            for ($page = 1; $page <= $this->http->maxPages(); ++$page) {
                $html = $this->http->getHtml($this->getName(), $this->url($profile, $keyword, $page));
                $offers = OfferListFinder::find($this->nextData($html), ['title', 'id']);
                if (null === $offers) {
                    throw PortalException::structureChanged($this->getName(), 'nie znaleziono listy ofert');
                }
                foreach ($offers as $o) {
                    yield $this->map($o);
                }
                if (\count($offers) < 20) {
                    break;
                }
            }
        }
    }

    private function url(SearchProfile $profile, string $keyword, int $page): string
    {
        $segments = [];
        $levels = array_map(static fn ($s) => match ($s) { 'mid' => 'mid', 'lead' => 'lead', default => $s }, $profile->getSeniorities());
        if ([] !== $levels) {
            $segments[] = implode(',', $levels).';p';
        }
        $modes = array_map(static fn ($m) => match ($m) {
            'remote' => 'zdalna',
            'hybrid' => 'hybrydowa',
            default => 'stacjonarna',
        }, $profile->getWorkModes());
        if ([] !== $modes) {
            $segments[] = implode(',', $modes).';rw';
        }
        $query = array_filter(['kw' => $keyword, 'pageNumber' => $page > 1 ? $page : null]);

        return self::BASE.'/filtry/'.implode('/', $segments).($query ? '?'.http_build_query($query) : '');
    }

    private function map(array $o): ScrapedOffer
    {
        $employer = $o['employer'] ?? $o['company'] ?? 'brak';
        if (\is_array($employer)) {
            $employer = $employer['name'] ?? 'brak';
        }
        $locations = [];
        foreach ((array) ($o['workplace'] ?? $o['workplaces'] ?? []) as $w) {
            $city = \is_array($w) ? ($w['location'] ?? $w['city'] ?? null) : $w;
            if (\is_string($city) && '' !== $city) {
                $locations[] = $city;
            }
        }
        $modes = array_map(static fn ($m) => mb_strtolower(\is_array($m) ? (string) ($m['value'] ?? $m['name'] ?? '') : (string) $m), (array) ($o['workModes'] ?? []));
        $workMode = match (true) {
            [] !== array_filter($modes, static fn ($m) => str_contains($m, 'remote') || str_contains($m, 'zdal')) => 'remote',
            [] !== array_filter($modes, static fn ($m) => str_contains($m, 'hybr')) => 'hybrid',
            [] !== $modes => 'office',
            default => null,
        };
        $levels = (array) ($o['positionLevels'] ?? []);
        $level = $levels[0] ?? null;
        if (\is_array($level)) {
            $level = $level['value'] ?? $level['name'] ?? null;
        }

        $salary = $o['salary'] ?? null;
        [$from, $to, $currency, $period] = [null, null, null, null];
        if (\is_array($salary)) {
            $from = isset($salary['from']) ? (float) $salary['from'] : null;
            $to = isset($salary['to']) ? (float) $salary['to'] : null;
            $currency = $salary['currencySymbol'] ?? $salary['currency'] ?? null;
            $period = \is_array($salary['timeUnit'] ?? null) ? ($salary['timeUnit']['longForm'] ?? null) : ($salary['timeUnit'] ?? null);
        } elseif (\is_string($salary)) {
            [$from, $to, $currency, $period] = SalaryText::parse($salary);
        }

        $tags = [];
        foreach ((array) ($o['technologies'] ?? []) as $t) {
            $tags[] = \is_array($t) ? (string) ($t['name'] ?? '') : (string) $t;
        }
        $slug = $o['offerUrlName'] ?? $o['urlName'] ?? $o['id'];
        $url = isset($o['url']) && str_starts_with((string) $o['url'], 'http') ? $o['url'] : self::BASE.'/szczegoly/praca/'.$slug;

        return new ScrapedOffer(
            externalId: (string) $o['id'],
            url: (string) $url,
            title: (string) $o['title'],
            company: (string) $employer,
            location: [] !== $locations ? implode(', ', \array_slice(array_unique($locations), 0, 4)) : null,
            workMode: $workMode,
            salaryFrom: self::monthly($from, $period),
            salaryTo: self::monthly($to, $period),
            salaryCurrency: self::currency($currency),
            contractType: self::contract(\is_array($o['typesOfContracts'][0] ?? null) ? ($o['typesOfContracts'][0]['name'] ?? null) : ($o['typesOfContracts'][0] ?? null)),
            tags: array_values(array_filter($tags)),
            seniority: self::seniority(\is_string($level) ? $level : null),
            publishedAt: self::date($o['publicationDateUtc'] ?? $o['publicationDate'] ?? null),
        );
    }

    private static function currency(?string $c): ?string
    {
        return match (mb_strtolower((string) $c)) {
            '' => null,
            'zł', 'zl', 'pln' => 'PLN',
            '€', 'eur' => 'EUR',
            '$', 'usd' => 'USD',
            default => strtoupper((string) $c),
        };
    }
}
