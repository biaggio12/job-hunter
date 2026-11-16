<?php

namespace App\Portal\Adapter;

use App\Entity\SearchProfile;
use App\Portal\AbstractPortalAdapter;
use App\Portal\PortalException;
use App\Portal\ScrapedOffer;

/** bulldogjob.pl — lista ofert z danych Next.js (__NEXT_DATA__). */
final class BulldogJobAdapter extends AbstractPortalAdapter
{
    private const BASE = 'https://bulldogjob.pl/companies/jobs';

    public function getCode(): string { return 'bulldogjob'; }
    public function getName(): string { return 'Bulldogjob'; }
    public function getHomepage(): string { return 'https://bulldogjob.pl'; }

    public function fetch(SearchProfile $profile): iterable
    {
        foreach ($this->keywords($profile) as $keyword) {
            $seen = 0;
            for ($page = 1; $page <= $this->http->maxPages(); ++$page) {
                $html = $this->http->getHtml($this->getName(), $this->url($profile, $keyword, $page));
                $props = self::path($this->nextData($html), 'props.pageProps');
                if (!\is_array($props) || !isset($props['jobs']) || !\is_array($props['jobs'])) {
                    throw PortalException::structureChanged($this->getName(), 'brak listy "jobs"');
                }
                foreach ($props['jobs'] as $job) {
                    if (\is_array($job) && isset($job['id'], $job['position'])) {
                        ++$seen;
                        yield $this->map($job);
                    }
                }
                if ([] === $props['jobs'] || $seen >= (int) ($props['totalCount'] ?? 0)) {
                    break;
                }
            }
        }
    }

    private function url(SearchProfile $profile, string $keyword, int $page): string
    {
        $segments = [];
        if ('' !== $keyword) {
            $segments[] = 'skills,'.rawurlencode($keyword);
        }
        $levels = array_map(static fn ($s) => 'mid' === $s ? 'medium' : $s, $profile->getSeniorities());
        if ([] !== $levels) {
            $segments[] = 'experienceLevel,'.implode(',', $levels);
        }
        if ($page > 1) {
            $segments[] = 'page,'.$page;
        }

        return self::BASE.([] === $segments ? '' : '/s/'.implode('/', $segments));
    }

    private function map(array $j): ScrapedOffer
    {
        $salary = $j['denominatedSalaryLong'] ?? [];
        [$from, $to] = [null, null];
        if (empty($salary['hidden']) && !empty($salary['money'])
            && preg_match_all('/\d[\d\s\x{00A0}]*/u', (string) $salary['money'], $m)) {
            $nums = array_map(static fn ($n) => (int) preg_replace('/\D/', '', $n), $m[0]);
            $from = $nums[0] ?? null;
            $to = $nums[1] ?? $from;
        }

        $remotePossible = $j['environment']['remotePossible'] ?? null;
        $workMode = match (true) {
            !empty($j['remote']) => 'remote',
            null !== $remotePossible && (int) $remotePossible > 0 => 'hybrid',
            default => 'office',
        };
        $contract = match (true) {
            !empty($j['contractB2b']) => 'b2b',
            !empty($j['contractEmployment']) => 'uop',
            default => null,
        };

        return new ScrapedOffer(
            externalId: (string) $j['id'],
            url: self::BASE.'/'.$j['id'],
            title: (string) $j['position'],
            company: (string) ($j['company']['name'] ?? 'brak'),
            location: $j['city'] ?? null,
            workMode: $workMode,
            salaryFrom: $from,
            salaryTo: $to,
            salaryCurrency: null !== $from ? strtoupper((string) ($salary['currency'] ?? 'PLN')) : null,
            contractType: $contract,
            tags: array_map('strval', $j['technologyTags'] ?? []),
            seniority: self::seniority($j['experienceLevel'] ?? null),
            publishedAt: null, // lista nie podaje daty publikacji
        );
    }
}
