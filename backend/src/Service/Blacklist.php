<?php

namespace App\Service;

use App\Entity\BlacklistedCompany;
use App\Repository\BlacklistedCompanyRepository;
use App\Repository\JobOfferRepository;
use App\Util\Text;
use Doctrine\ORM\EntityManagerInterface;

/** Czarna lista firm (US-D5). */
class Blacklist
{
    /** @var array<string, true>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly BlacklistedCompanyRepository $repository,
        private readonly JobOfferRepository $offers,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function contains(string $company): bool
    {
        if (null === $this->cache) {
            $this->cache = [];
            foreach ($this->repository->findAll() as $b) {
                $this->cache[$b->getNormalizedName()] = true;
            }
        }

        return isset($this->cache[Text::normalizeCompany($company)]);
    }

    /** @return array{entry: BlacklistedCompany, hidden: int} */
    public function add(string $company): array
    {
        $normalized = Text::normalizeCompany($company);
        if ('' === $normalized) {
            throw new \InvalidArgumentException('Nazwa firmy nie może być pusta.');
        }
        $entry = $this->repository->findOneBy(['normalizedName' => $normalized]);
        if (null === $entry) {
            $entry = new BlacklistedCompany(trim($company), $normalized);
            $this->em->persist($entry);
        }
        $hidden = $this->offers->hideCompany($normalized, Text::normalizeCompany(...));
        $this->em->flush();
        $this->cache = null;

        return ['entry' => $entry, 'hidden' => $hidden];
    }

    public function remove(BlacklistedCompany $entry): void
    {
        $this->em->remove($entry);
        $this->em->flush();
        $this->cache = null;
    }
}
