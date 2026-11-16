<?php

namespace App\Portal\Adapter;

use App\Entity\SearchProfile;
use App\Portal\AbstractPortalAdapter;
use App\Portal\PortalException;
use App\Portal\SalaryText;
use App\Portal\ScrapedOffer;

/**
 * it.pracuj.pl — lista ofert z danych Next.js („groupedOffers”).
 *
 * Uwaga: portal stoi za ochroną anty-botową Cloudflare. Gdy ją aktywuje, adapter zgłasza
 * czytelny błąd zamiast próbować ją obejść.
 */
final class PracujAdapter extends AbstractPortalAdapter
{
    private const BASE = 'https://it.pracuj.pl/praca';
    private const PER_PAGE = 50;

    public function getCode(): string { return 'pracuj'; }
    public function getName(): string { return 'Pracuj.pl'; }
    public function getHomepage(): string { return 'https://it.pracuj.pl'; }

    public function fetch(SearchProfile $profile): iterable
    {
        foreach ($this->keywords($profile) as $keyword) {
            for ($page = 1; $page <= $this->http->maxPages(); ++$page) {
                $html = $this->http->getHtml($this->getName(), $this->url($profile, $keyword, $page));
                $groups = OfferListFinder::findByKey($this->nextData($html), 'groupedOffers');
                if (null === $groups) {
                    throw PortalException::structureChanged($this->getName(), 'nie znaleziono "groupedOffers"');
                }
                foreach ($groups as $group) {
                    if (\is_array($group) && isset($group['jobTitle'])) {
                        yield from $this->map($group);
                    }
                }
                if (\count($groups) < self::PER_PAGE) {
                    break;
                }
            }
        }
    }

    private function url(SearchProfile $profile, string $keyword, int $page): string
    {
        $path = '' !== $keyword ? '/'.rawurlencode($keyword).';kw' : '';
        $query = [];
        $modes = array_map(static fn ($m) => match ($m) {
            'remote' => 'home-office',
            'hybrid' => 'hybrid',
            default => 'full-office',
        }, $profile->getWorkModes());
        if ([] !== $modes) {
            $query['wm'] = implode(',', $modes);
        }
        // Identyfikatory poziomów stanowisk w pracuj.pl.
        $levels = array_filter(array_map(static fn ($s) => match ($s) {
            'junior' => '17',
            'mid' => '4',
            'senior' => '18',
            'lead' => '19',
            default => null,
        }, $profile->getSeniorities()));
        if ([] !== $levels) {
            $query['et'] = implode(',', $levels);
        }
        if ($page > 1) {
            $query['pn'] = $page;
        }

        return self::BASE.$path.($query ? '?'.http_build_query($query) : '');
    }

    /** @return iterable<ScrapedOffer> jedno ogłoszenie na każdą lokalizację w grupie */
    private function map(array $g): iterable
    {
        [$from, $to, $currency, $period] = SalaryText::parse((string) ($g['salaryDisplayText'] ?? ''));
        $modes = array_map('mb_strtolower', array_map('strval', (array) ($g['workModes'] ?? [])));
        // Wartości po polsku: „Praca zdalna”, „Praca hybrydowa”, „Praca stacjonarna”, „Praca mobilna”.
        $workMode = match (true) {
            // Oferta dopuszczająca pracę zdalną jest traktowana jako zdalna, nawet jeśli dopuszcza też hybrydę.
            [] !== array_filter($modes, static fn ($m) => str_contains($m, 'zdaln') || str_contains($m, 'home') || str_contains($m, 'remote')) => 'remote',
            [] !== array_filter($modes, static fn ($m) => str_contains($m, 'hybr')) => 'hybrid',
            [] !== $modes => 'office',
            default => null,
        };
        $level = \is_array($g['positionLevels'] ?? null) ? ($g['positionLevels'][0] ?? null) : null;
        $contract = \is_array($g['typesOfContract'] ?? null) ? ($g['typesOfContract'][0] ?? null) : null;

        $offers = (array) ($g['offers'] ?? []);
        $first = $offers[0] ?? [];
        $locations = array_values(array_unique(array_filter(array_map(
            static fn ($o) => \is_array($o) ? ($o['displayWorkplace'] ?? null) : null,
            $offers,
        ))));
        $id = (string) ($g['groupId'] ?? $first['partitionId'] ?? '');
        $url = (string) ($first['offerAbsoluteUri'] ?? '');
        if ('' === $id || '' === $url) {
            return;
        }

        yield new ScrapedOffer(
            externalId: $id,
            url: $url,
            title: (string) $g['jobTitle'],
            company: (string) ($g['companyName'] ?? 'brak'),
            location: [] !== $locations ? implode(', ', \array_slice($locations, 0, 4)) : null,
            workMode: $workMode,
            salaryFrom: self::monthly($from, $period),
            salaryTo: self::monthly($to, $period),
            salaryCurrency: $currency,
            contractType: self::contract(\is_string($contract) ? $contract : null),
            tags: array_values(array_map('strval', (array) ($g['technologies'] ?? []))),
            seniority: self::seniority(\is_string($level) ? $level : null),
            publishedAt: self::date($g['lastPublicated'] ?? null),
        );
    }
}
