<?php

namespace App\Repository;

use App\Entity\JobOffer;
use App\Entity\Portal;
use App\Enum\ApplicationStatus;
use App\Enum\RecruitmentStage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JobOffer> */
class JobOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobOffer::class);
    }

    /**
     * Filtrowana i stronicowana lista ogłoszeń (US-C1, US-C2).
     *
     * @param array{
     *   status?: ?string, portal?: ?string, workMode?: ?string, location?: ?string,
     *   minSalary?: ?int, tech?: ?string, q?: ?string, showExpired?: bool, unseen?: bool
     * } $f
     * @return array{items: list<JobOffer>, total: int}
     */
    public function search(array $f, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('o')
            ->addSelect('p')
            ->addSelect('COALESCE(o.publishedAt, o.firstSeenAt) AS HIDDEN sortDate')
            ->join('o.portal', 'p')
            ->orderBy('sortDate', 'DESC')
            ->addOrderBy('o.id', 'DESC');

        $status = $f['status'] ?? null;
        if (null === $status || '' === $status) {
            // Domyślnie: bez odrzuconych i ukrytych (US-D2).
            $qb->andWhere('o.status IN (:statuses)')
                ->setParameter('statuses', [ApplicationStatus::None, ApplicationStatus::Applied]);
        } elseif ('all' !== $status) {
            $qb->andWhere('o.status = :status')->setParameter('status', ApplicationStatus::from($status));
        }

        if (empty($f['showExpired'])) {
            $qb->andWhere('o.expired = false');
        }
        if (!empty($f['unseen'])) {
            $qb->andWhere('o.viewedAt IS NULL');
        }
        if (!empty($f['portal'])) {
            $qb->andWhere('p.code = :portal')->setParameter('portal', $f['portal']);
        }
        if (!empty($f['workMode'])) {
            $qb->andWhere('o.workMode = :workMode')->setParameter('workMode', $f['workMode']);
        }
        if (!empty($f['location'])) {
            $qb->andWhere('LOWER(o.location) LIKE :location')
                ->setParameter('location', '%'.mb_strtolower($f['location']).'%');
        }
        if (!empty($f['minSalary'])) {
            $qb->andWhere('COALESCE(o.salaryTo, o.salaryFrom) >= :minSalary')
                ->setParameter('minSalary', (int) $f['minSalary']);
        }
        if (!empty($f['q'])) {
            $qb->andWhere('(LOWER(o.title) LIKE :q OR LOWER(o.company) LIKE :q)')
                ->setParameter('q', '%'.mb_strtolower($f['q']).'%');
        }
        if (!empty($f['tech'])) {
            // Tagi są w kolumnie JSON — filtrujemy po identyfikatorach z natywnego zapytania.
            $ids = $this->idsWithTag($f['tech']);
            $qb->andWhere('o.id IN (:techIds)')->setParameter('techIds', [] === $ids ? [0] : $ids);
        }

        $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);
        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: false);

        return ['items' => iterator_to_array($paginator->getIterator(), false), 'total' => \count($paginator)];
    }

    /** @return list<int> */
    private function idsWithTag(string $tag): array
    {
        $sql = 'SELECT id FROM job_offer WHERE EXISTS (
                    SELECT 1 FROM json_array_elements_text(tags) t WHERE LOWER(t) = LOWER(:tag)
                )';

        return array_map('intval', $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, ['tag' => $tag]));
    }

    /** @return list<array{name: string, count: int}> najpopularniejsze tagi */
    public function topTags(int $limit = 40): array
    {
        $sql = "SELECT t AS name, COUNT(*) AS count
                FROM job_offer, json_array_elements_text(tags) t
                WHERE expired = false AND status IN ('none', 'applied')
                GROUP BY t ORDER BY count DESC, t ASC LIMIT ".(int) $limit;

        return array_map(
            static fn (array $r) => ['name' => (string) $r['name'], 'count' => (int) $r['count']],
            $this->getEntityManager()->getConnection()->fetchAllAssociative($sql),
        );
    }

    public function countUnseen(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.viewedAt IS NULL')
            ->andWhere('o.expired = false')
            ->andWhere('o.status = :none')
            ->setParameter('none', ApplicationStatus::None)
            ->getQuery()->getSingleScalarResult();
    }

    /**
     * @param list<string> $externalIds
     * @return array<string, JobOffer> klucz = externalId
     */
    public function findByPortalAndExternalIds(Portal $portal, array $externalIds): array
    {
        if ([] === $externalIds) {
            return [];
        }
        $result = [];
        foreach (array_chunk($externalIds, 500) as $chunk) {
            foreach ($this->findBy(['portal' => $portal, 'externalId' => $chunk]) as $offer) {
                $result[$offer->getExternalId()] = $offer;
            }
        }

        return $result;
    }

    /**
     * Ogłoszenia portalu niewykryte w danym skanie (US-B6).
     *
     * @param list<int> $seenIds
     * @return list<JobOffer>
     */
    public function findNotSeen(Portal $portal, array $seenIds): array
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.portal = :portal')
            ->andWhere('o.expired = false')
            ->setParameter('portal', $portal);
        if ([] !== $seenIds) {
            $qb->andWhere('o.id NOT IN (:seen)')->setParameter('seen', $seenIds);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<JobOffer> */
    public function findPendingNotifications(int $limit = 200): array
    {
        return $this->createQueryBuilder('o')
            ->addSelect('p')
            ->join('o.portal', 'p')
            ->andWhere('o.notificationPending = true')
            ->andWhere('o.status = :none')
            ->setParameter('none', ApplicationStatus::None)
            ->orderBy('o.firstSeenAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    /**
     * Pozostałe ogłoszenia z tej samej grupy duplikatów (inne portale) — US-B5.
     *
     * @return list<JobOffer>
     */
    public function findDuplicatesOf(JobOffer $offer): array
    {
        if ('' === $offer->getDuplicateKey()) {
            return [];
        }

        return $this->createQueryBuilder('o')
            ->addSelect('p')
            ->join('o.portal', 'p')
            ->andWhere('o.duplicateKey = :key')
            ->andWhere('o.id != :id')
            ->andWhere('o.portal != :portal')
            ->setParameter('key', $offer->getDuplicateKey())
            ->setParameter('id', $offer->getId())
            ->setParameter('portal', $offer->getPortal())
            ->getQuery()->getResult();
    }

    /**
     * Dla listy ogłoszeń: ile duplikatów ma każde i czy w grupie jest już aplikacja.
     *
     * @param list<JobOffer> $offers
     * @return array<int, array{count: int, applied: bool}>
     */
    public function duplicateInfo(array $offers): array
    {
        $keys = array_values(array_unique(array_filter(array_map(static fn (JobOffer $o) => $o->getDuplicateKey(), $offers))));
        if ([] === $keys) {
            return [];
        }
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT id, portal_id, duplicate_key, status FROM job_offer WHERE duplicate_key IN (:keys)',
            ['keys' => $keys],
            ['keys' => ArrayParameterType::STRING],
        );
        $byKey = [];
        foreach ($rows as $r) {
            $byKey[$r['duplicate_key']][] = $r;
        }

        $info = [];
        foreach ($offers as $offer) {
            $count = 0;
            $applied = false;
            foreach ($byKey[$offer->getDuplicateKey()] ?? [] as $r) {
                if ((int) $r['id'] === $offer->getId() || (int) $r['portal_id'] === $offer->getPortal()->getId()) {
                    continue;
                }
                ++$count;
                $applied = $applied || 'applied' === $r['status'];
            }
            if ($count > 0) {
                $info[$offer->getId()] = ['count' => $count, 'applied' => $applied];
            }
        }

        return $info;
    }

    /** @return list<JobOffer> ogłoszenia w procesie rekrutacji (kanban, US-D3) */
    public function findInPipeline(): array
    {
        return $this->createQueryBuilder('o')
            ->addSelect('p')
            ->join('o.portal', 'p')
            ->andWhere('o.stage IS NOT NULL')
            ->orderBy('o.appliedAt', 'DESC')
            ->getQuery()->getResult();
    }

    /** Ukrywa nieprzetworzone ogłoszenia firmy z czarnej listy (US-D5). */
    public function hideCompany(string $normalizedCompany, callable $normalizer): int
    {
        $count = 0;
        foreach ($this->findBy(['status' => ApplicationStatus::None]) as $offer) {
            if ($normalizer($offer->getCompany()) === $normalizedCompany) {
                $offer->setStatus(ApplicationStatus::Hidden, new \DateTimeImmutable());
                ++$count;
            }
        }

        return $count;
    }

    /** @return array<string, int> liczba ogłoszeń w poszczególnych etapach */
    public function countByStage(): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('o.stage AS stage, COUNT(o.id) AS c')
            ->andWhere('o.stage IS NOT NULL')
            ->groupBy('o.stage')
            ->getQuery()->getArrayResult();
        $out = array_fill_keys(array_map(static fn (RecruitmentStage $s) => $s->value, RecruitmentStage::cases()), 0);
        foreach ($rows as $r) {
            $stage = $r['stage'] instanceof RecruitmentStage ? $r['stage']->value : (string) $r['stage'];
            $out[$stage] = (int) $r['c'];
        }

        return $out;
    }
}
