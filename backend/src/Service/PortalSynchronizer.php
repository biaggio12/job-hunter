<?php

namespace App\Service;

use App\Entity\Portal;
use App\Entity\SearchProfile;
use App\Portal\PortalAdapterRegistry;
use App\Repository\PortalRepository;
use App\Repository\SearchProfileRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Synchronizuje tabelę portali z adapterami w kodzie i zakłada dane startowe
 * (ustawienia + domyślny profil wyszukiwania).
 */
class PortalSynchronizer
{
    public function __construct(
        private readonly PortalAdapterRegistry $registry,
        private readonly PortalRepository $portals,
        private readonly SearchProfileRepository $profiles,
        private readonly SettingsProvider $settings,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @return list<string> kody nowo dodanych portali */
    public function sync(): array
    {
        $added = [];
        foreach ($this->registry->all() as $code => $adapter) {
            $portal = $this->portals->findOneBy(['code' => $code]);
            if (null === $portal) {
                $portal = new Portal($code, $adapter->getName(), $adapter->getHomepage());
                $this->em->persist($portal);
                $added[] = $code;
            } else {
                $portal->setName($adapter->getName());
                $portal->setHomepage($adapter->getHomepage());
            }
        }
        $this->em->flush();

        return $added;
    }

    public function seedDefaults(): bool
    {
        $this->settings->get();
        if ($this->profiles->count([]) > 0) {
            return false;
        }
        $profile = new SearchProfile('PHP / Symfony — zdalnie, mid/senior');
        $profile->setKeywords(['symfony', 'php']);
        $profile->setWorkModes(['remote']);
        $profile->setSeniorities(['mid', 'senior']);
        $this->em->persist($profile);
        $this->em->flush();

        return true;
    }
}
