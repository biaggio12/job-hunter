<?php

namespace App\Repository;

use App\Entity\Portal;
use App\Entity\ScanRun;
use App\Enum\ScanStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ScanRun> */
class ScanRunRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScanRun::class);
    }

    /** @return list<ScanRun> */
    public function findRecent(int $limit, ?Portal $portal = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->addSelect('p')
            ->join('s.portal', 'p')
            ->orderBy('s.createdAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults($limit);
        if (null !== $portal) {
            $qb->andWhere('s.portal = :portal')->setParameter('portal', $portal);
        }

        return $qb->getQuery()->getResult();
    }

    /** Skany oczekujące/trwające — do wskaźnika „skan trwa” w UI. */
    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.status IN (:active)')
            ->andWhere('s.createdAt > :since')
            ->setParameter('active', [ScanStatus::Queued, ScanStatus::Running])
            ->setParameter('since', new \DateTimeImmutable('-1 hour'))
            ->getQuery()->getSingleScalarResult();
    }
}
