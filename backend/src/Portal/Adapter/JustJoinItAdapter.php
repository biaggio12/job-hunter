<?php

namespace App\Portal\Adapter;

use App\Entity\SearchProfile;
use App\Portal\AbstractPortalAdapter;
use App\Portal\PortalException;
use App\Portal\ScrapedOffer;

/** justjoin.it — publiczne API JSON używane przez ich frontend. */
final class JustJoinItAdapter extends AbstractPortalAdapter
{
    private const API = 'https://justjoin.it/api/candidate-api/offers';
    private const PER_PAGE = 50;

    public function getCode(): string { return 'justjoin'; }
    public function getName(): string { return 'Just Join IT'; }
    public function getHomepage(): string { return 'https://justjoin.it'; }

    public function fetch(SearchProfile $profile): iterable
    {
        foreach ($this->keywords($profile) as $keyword) {
            $from = 0;
            for ($page = 1; $page <= $this->http->maxPages(); ++$page) {
                $data = $this->http->getJson($this->getName(), self::API.'?'.$this->query($profile, $keyword, $from));
                if (!isset($data['data']) || !\is_array($data['data'])) {
                    throw PortalException::structureChanged($this->getName(), 'brak pola "data"');
                }
                foreach ($data['data'] as $item) {
                    if (\is_array($item) && isset($item['guid'], $item['slug'], $item['title'])) {
                        yield $this->map($item);
                    }
                }
                $next = $data['meta']['next']['cursor'] ?? null;
                if (null === $next || [] === $data['data']) {
                    break;
                }
                $from = (int) $next;
            }
        }
    }

    private function query(SearchProfile $profile, string $keyword, int $from): string
    {
        $params = [
            'sortBy' => 'publishedAt',
            'orderBy' => 'descending',
            'itemsCount' => self::PER_PAGE,
            'from' => $from,
        ];
        if ('' !== $keyword) {
            $params['keywords'] = $keyword;
        }
        // Jedno miasto i brak pracy zdalnej → filtr po stronie API; pozostałe przypadki filtruje ScanService.
        if (1 === \count($profile->getLocations()) && !\in_array('remote', $profile->getWorkModes(), true)) {
            $params['city'] = $profile->getLocations()[0];
        }
        $query = http_build_query($params);
        foreach ($profile->getSeniorities() as $s) {
            $query .= '&experienceLevels='.urlencode('lead' === $s ? 'manager' : $s);
        }

        return $query;
    }

    private function map(array $o): ScrapedOffer
    {
        $salary = null;
        foreach ($o['employmentTypes'] ?? [] as $et) {
            if (isset($et['from']) || isset($et['to'])) {
                $salary = $et;
                // Preferujemy B2B (najczęściej najwyższe widełki), ale bierzemy pierwszą dostępną.
                if ('b2b' === ($et['type'] ?? null)) {
                    break;
                }
            }
        }
        $firstType = $o['employmentTypes'][0]['type'] ?? null;

        $cities = array_values(array_unique(array_filter(array_map(
            static fn ($l) => $l['city'] ?? null,
            $o['locations'] ?? [],
        ))));
        $tags = array_map(static fn ($s) => (string) $s['name'], array_merge($o['requiredSkills'] ?? [], $o['niceToHaveSkills'] ?? []));

        return new ScrapedOffer(
            externalId: (string) $o['guid'],
            url: 'https://justjoin.it/job-offer/'.$o['slug'],
            title: (string) $o['title'],
            company: (string) ($o['companyName'] ?? 'brak'),
            location: [] !== $cities ? implode(', ', \array_slice($cities, 0, 4)) : ($o['city'] ?? null),
            workMode: \in_array($o['workplaceType'] ?? null, ['remote', 'hybrid', 'office'], true) ? $o['workplaceType'] : null,
            // API podaje from/to w przeliczeniu miesięcznym także dla stawek godzinowych.
            salaryFrom: isset($salary['from']) ? (int) round($salary['from']) : null,
            salaryTo: isset($salary['to']) ? (int) round($salary['to']) : null,
            salaryCurrency: isset($salary['currency']) ? strtoupper($salary['currency']) : null,
            contractType: self::contract($salary['type'] ?? $firstType),
            tags: $tags,
            seniority: self::seniority($o['experienceLevel'] ?? null),
            publishedAt: self::date($o['publishedAt'] ?? null),
        );
    }
}
