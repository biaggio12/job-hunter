<?php

namespace App\Api;

use App\Entity\BlacklistedCompany;
use App\Entity\JobOffer;
use App\Entity\Portal;
use App\Entity\ScanRun;
use App\Entity\SearchProfile;
use App\Entity\StageChange;

/** Zamiana encji na tablice JSON dla API. */
final class Presenter
{
    /** @param array{count: int, applied: bool}|null $duplicates */
    public static function offer(JobOffer $o, ?array $duplicates = null): array
    {
        return [
            'id' => $o->getId(),
            'portal' => ['code' => $o->getPortal()->getCode(), 'name' => $o->getPortal()->getName()],
            'externalId' => $o->getExternalId(),
            'url' => $o->getUrl(),
            'title' => $o->getTitle(),
            'company' => $o->getCompany(),
            'location' => $o->getLocation(),
            'workMode' => $o->getWorkMode(),
            'salaryFrom' => $o->getSalaryFrom(),
            'salaryTo' => $o->getSalaryTo(),
            'salaryCurrency' => $o->getSalaryCurrency(),
            'contractType' => $o->getContractType(),
            'tags' => $o->getTags(),
            'seniority' => $o->getSeniority(),
            'publishedAt' => self::date($o->getPublishedAt()),
            'firstSeenAt' => self::date($o->getFirstSeenAt()),
            'lastSeenAt' => self::date($o->getLastSeenAt()),
            'expired' => $o->isExpired(),
            'status' => $o->getStatus()->value,
            'appliedAt' => self::date($o->getAppliedAt()),
            'stage' => $o->getStage()?->value,
            'viewed' => null !== $o->getViewedAt(),
            'hasNote' => null !== $o->getNote(),
            'notifiedAt' => self::date($o->getNotifiedAt()),
            'duplicates' => $duplicates ?? ['count' => 0, 'applied' => false],
        ];
    }

    /** @param list<JobOffer> $duplicates */
    public static function offerDetails(JobOffer $o, array $duplicates): array
    {
        $applied = array_any($duplicates, static fn (JobOffer $d) => 'applied' === $d->getStatus()->value);

        return self::offer($o, ['count' => \count($duplicates), 'applied' => $applied]) + [
            'note' => $o->getNote(),
            'missedScans' => $o->getMissedScans(),
            'stageHistory' => array_map(static fn (StageChange $c) => [
                'from' => $c->getFromStage()?->value,
                'to' => $c->getToStage()?->value,
                'changedAt' => self::date($c->getChangedAt()),
            ], $o->getStageHistory()->toArray()),
            'duplicateOffers' => array_map(static fn (JobOffer $d) => [
                'id' => $d->getId(),
                'title' => $d->getTitle(),
                'company' => $d->getCompany(),
                'portal' => ['code' => $d->getPortal()->getCode(), 'name' => $d->getPortal()->getName()],
                'status' => $d->getStatus()->value,
                'url' => $d->getUrl(),
            ], $duplicates),
        ];
    }

    public static function portal(Portal $p, ?ScanRun $lastRun = null, bool $hasAdapter = true): array
    {
        return [
            'id' => $p->getId(),
            'code' => $p->getCode(),
            'name' => $p->getName(),
            'homepage' => $p->getHomepage(),
            'enabled' => $p->isEnabled(),
            'hasAdapter' => $hasAdapter,
            'lastSuccessfulScanAt' => self::date($p->getLastSuccessfulScanAt()),
            'consecutiveFailures' => $p->getConsecutiveFailures(),
            'lastRun' => null === $lastRun ? null : self::scan($lastRun),
        ];
    }

    public static function scan(ScanRun $s): array
    {
        return [
            'id' => $s->getId(),
            'portal' => ['code' => $s->getPortal()->getCode(), 'name' => $s->getPortal()->getName()],
            'trigger' => $s->getTrigger()->value,
            'status' => $s->getStatus()->value,
            'createdAt' => self::date($s->getCreatedAt()),
            'startedAt' => self::date($s->getStartedAt()),
            'finishedAt' => self::date($s->getFinishedAt()),
            'foundCount' => $s->getFoundCount(),
            'newCount' => $s->getNewCount(),
            'error' => $s->getError(),
        ];
    }

    public static function profile(SearchProfile $p): array
    {
        return [
            'id' => $p->getId(),
            'name' => $p->getName(),
            'keywords' => $p->getKeywords(),
            'workModes' => $p->getWorkModes(),
            'seniorities' => $p->getSeniorities(),
            'locations' => $p->getLocations(),
            'active' => $p->isActive(),
            'createdAt' => self::date($p->getCreatedAt()),
        ];
    }

    public static function blacklisted(BlacklistedCompany $b): array
    {
        return ['id' => $b->getId(), 'name' => $b->getName(), 'createdAt' => self::date($b->getCreatedAt())];
    }

    private static function date(?\DateTimeImmutable $d): ?string
    {
        return $d?->format(\DATE_ATOM);
    }
}
