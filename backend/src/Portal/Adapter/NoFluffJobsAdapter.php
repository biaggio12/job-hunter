<?php

namespace App\Portal\Adapter;

use App\Entity\SearchProfile;
use App\Portal\AbstractPortalAdapter;
use App\Portal\PortalException;
use App\Portal\ScrapedOffer;
use App\Util\Text;

/** nofluffjobs.com — API wyszukiwania (JSON, POST). */
final class NoFluffJobsAdapter extends AbstractPortalAdapter
{
    private const API = 'https://nofluffjobs.com/api/search/posting';

    public function getCode(): string { return 'nofluffjobs'; }
    public function getName(): string { return 'No Fluff Jobs'; }
    public function getHomepage(): string { return 'https://nofluffjobs.com'; }

    public function fetch(SearchProfile $profile): iterable
    {
        foreach ($this->keywords($profile) as $keyword) {
            for ($page = 1; $page <= $this->http->maxPages(); ++$page) {
                $url = self::API.'?'.http_build_query([
                    'page' => $page,
                    'salaryCurrency' => 'PLN',
                    'salaryPeriod' => 'month',
                    'region' => 'pl',
                    'language' => 'pl-PL',
                ]);
                $data = $this->http->postJson($this->getName(), $url, [
                    'criteriaSearch' => $this->criteria($profile, $keyword),
                    'page' => $page,
                ]);
                if (!isset($data['postings']) || !\is_array($data['postings'])) {
                    throw PortalException::structureChanged($this->getName(), 'brak pola "postings"');
                }
                foreach ($data['postings'] as $p) {
                    if (\is_array($p) && isset($p['id'], $p['title'], $p['url'])) {
                        yield $this->map($p);
                    }
                }
                if ([] === $data['postings'] || $page >= (int) ($data['totalPages'] ?? 1)) {
                    break;
                }
            }
        }
    }

    /** @return array<string, list<string>> */
    private function criteria(SearchProfile $profile, string $keyword): array
    {
        $criteria = [];
        if ('' !== $keyword) {
            $criteria['keyword'] = [$keyword];
        }
        $cities = array_map(static fn ($c) => Text::slug($c), $profile->getLocations());
        $modes = $profile->getWorkModes();
        if (\in_array('remote', $modes, true) && ([] !== $cities || ['remote'] === $modes)) {
            $cities[] = 'remote';
        }
        if ([] !== $cities) {
            $criteria['city'] = array_values(array_unique($cities));
        }
        $seniority = array_map(static fn ($s) => 'lead' === $s ? 'expert' : $s, $profile->getSeniorities());
        if ([] !== $seniority) {
            $criteria['seniority'] = $seniority;
        }

        return $criteria;
    }

    private function map(array $p): ScrapedOffer
    {
        $loc = $p['location'] ?? [];
        $places = $loc['places'] ?? [];
        $cities = [];
        $hasRemotePlace = false;
        foreach ($places as $place) {
            $city = $place['city'] ?? null;
            if (null === $city) {
                continue;
            }
            if ('remote' === mb_strtolower($city)) {
                $hasRemotePlace = true;
            } else {
                $cities[] = $city;
            }
        }
        $cities = array_values(array_unique($cities));

        $workMode = match (true) {
            !empty($loc['fullyRemote']) || !empty($p['fullyRemote']) => 'remote',
            !empty($loc['hybridDesc']) => 'hybrid',
            $hasRemotePlace => 'remote',
            default => 'office',
        };

        $salary = $p['salary'] ?? [];
        $tags = [];
        foreach ($p['tiles']['values'] ?? [] as $tile) {
            if ('requirement' === ($tile['type'] ?? null)) {
                $tags[] = (string) $tile['value'];
            }
        }
        if (!empty($p['technology'])) {
            array_unshift($tags, (string) $p['technology']);
        }

        return new ScrapedOffer(
            externalId: (string) $p['id'],
            url: 'https://nofluffjobs.com/pl/job/'.$p['url'],
            title: (string) $p['title'],
            company: (string) ($p['name'] ?? 'brak'),
            location: [] !== $cities ? implode(', ', \array_slice($cities, 0, 4)) : ('remote' === $workMode ? 'Zdalnie' : null),
            workMode: $workMode,
            salaryFrom: self::monthly(isset($salary['from']) ? (float) $salary['from'] : null, $salary['period'] ?? null),
            salaryTo: self::monthly(isset($salary['to']) ? (float) $salary['to'] : null, $salary['period'] ?? null),
            salaryCurrency: isset($salary['currency']) ? strtoupper($salary['currency']) : null,
            contractType: self::contract($salary['type'] ?? null),
            tags: $tags,
            seniority: self::seniority($p['seniority'][0] ?? null),
            publishedAt: self::date($p['posted'] ?? null),
        );
    }
}
