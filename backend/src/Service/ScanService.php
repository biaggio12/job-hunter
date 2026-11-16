<?php

namespace App\Service;

use App\Entity\JobOffer;
use App\Entity\Portal;
use App\Entity\ScanRun;
use App\Enum\ApplicationStatus;
use App\Enum\ScanStatus;
use App\Enum\ScanTrigger;
use App\Notification\NotificationDispatcher;
use App\Portal\PortalAdapterRegistry;
use App\Portal\PortalException;
use App\Portal\ScrapedOffer;
use App\Repository\JobOfferRepository;
use App\Repository\PortalRepository;
use App\Repository\SearchProfileRepository;
use App\Util\Text;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;

/**
 * Skan pojedynczego portalu (US-B1…B6, US-F1, US-F2).
 * Każdy portal jest skanowany osobno, a wyjątek jednego adaptera nie wpływa na pozostałe.
 */
class ScanService
{
    public function __construct(
        private readonly PortalAdapterRegistry $registry,
        private readonly ManagerRegistry $doctrine,
        private readonly OfferMatcher $matcher,
        private readonly Blacklist $blacklist,
        private readonly SettingsProvider $settings,
        private readonly NotificationDispatcher $notifications,
        private readonly LoggerInterface $logger,
        private readonly LockFactory $lockFactory,
        #[Autowire('%env(int:EXPIRE_AFTER_MISSED_SCANS)%')]
        private readonly int $expireAfterMissedScans,
        #[Autowire('%env(int:SCAN_FAILURE_ALERT_THRESHOLD)%')]
        private readonly int $failureAlertThreshold,
    ) {
    }

    /**
     * Tworzy zakolejkowane skany dla wskazanego portalu lub wszystkich włączonych.
     *
     * @return list<ScanRun>
     */
    public function createRuns(?string $portalCode, ScanTrigger $trigger): array
    {
        $em = $this->em();
        if (null !== $portalCode) {
            $portal = $this->portals()->findOneBy(['code' => $portalCode])
                ?? throw new \InvalidArgumentException(\sprintf('Nieznany portal "%s".', $portalCode));
            $portals = [$portal];
        } else {
            $portals = $this->portals()->findBy(['enabled' => true], ['name' => 'ASC']);
        }

        $runs = [];
        foreach ($portals as $portal) {
            if (!$this->registry->has($portal->getCode())) {
                continue; // portal bez adaptera (usunięty z kodu)
            }
            $run = new ScanRun($portal, $trigger);
            $em->persist($run);
            $runs[] = $run;
        }
        $em->flush();

        return $runs;
    }

    public function run(int $scanRunId): ?ScanRun
    {
        $em = $this->em();
        $run = $em->find(ScanRun::class, $scanRunId);
        if (null === $run || $run->getStatus()->isFinished()) {
            return $run;
        }
        $portal = $run->getPortal();

        // Jeden skan danego portalu naraz (API, worker i konsola współdzielą blokadę w PostgreSQL).
        $lock = $this->lockFactory->createLock('scan-portal-'.$portal->getCode(), 3600);
        if (!$lock->acquire()) {
            $run->fail('Pominięto — inny skan tego portalu właśnie trwa.');
            $em->flush();

            return $run;
        }

        try {
            return $this->doRun($run, $portal);
        } finally {
            $lock->release();
        }
    }

    private function doRun(ScanRun $run, Portal $portal): ScanRun
    {
        $em = $this->em();
        $run->start();
        $em->flush();

        $profiles = $this->profiles()->findBy(['active' => true]);
        if ([] === $profiles) {
            $run->succeed(0, 0, 'Brak aktywnych profili wyszukiwania — nic nie pobrano.');
            $em->flush();

            return $run;
        }

        try {
            $adapter = $this->registry->get($portal->getCode());
            /** @var array<string, ScrapedOffer> $collected */
            $collected = [];
            $errors = [];
            foreach ($profiles as $profile) {
                try {
                    foreach ($adapter->fetch($profile) as $scraped) {
                        if ($this->matcher->matchesProfile($scraped, $profile)) {
                            $collected[$scraped->externalId] ??= $scraped;
                        }
                    }
                } catch (PortalException|\Symfony\Contracts\HttpClient\Exception\ExceptionInterface $e) {
                    $errors[] = \sprintf('[%s] %s', $profile->getName(), $e->getMessage());
                    $this->logger->warning('Skan {portal}/{profile}: {error}', ['portal' => $portal->getCode(), 'profile' => $profile->getName(), 'error' => $e->getMessage()]);
                }
            }

            if ([] === $collected && [] !== $errors) {
                throw new PortalException(implode("\n", array_unique($errors)));
            }

            $initialImport = null === $portal->getLastSuccessfulScanAt();
            [$newCount, $seenIds] = $this->store($portal, $collected, $initialImport);
            $now = new \DateTimeImmutable();

            if ([] !== $collected) {
                // Wygasanie tylko przy kompletnym skanie (bez błędów żadnego profilu).
                if ([] === $errors) {
                    $this->expireMissing($portal, $seenIds);
                }
                $portal->recordSuccess($now);
                $warning = [] === $errors ? null : implode("\n", $errors);
                if ($initialImport && $newCount > 0) {
                    $warning = trim(($warning ?? '')."\nPierwszy import — bez powiadomień o istniejących ogłoszeniach.");
                }
                $run->succeed(\count($collected), $newCount, $warning);
            } else {
                // 0 ogłoszeń traktujemy jako możliwą awarię adaptera (US-F2), ale skan się udał.
                $portal->recordFailure();
                $run->succeed(0, 0, 'Portal nie zwrócił żadnych ogłoszeń — sprawdź profil lub adapter.');
            }
            $this->em()->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Skan {portal} nieudany: {error}', ['portal' => $portal->getCode(), 'error' => $e->getMessage(), 'exception' => $e]);
            [$run, $portal] = $this->reloadAfterError($run, $portal);
            $run->fail($e->getMessage());
            $portal->recordFailure();
            $this->em()->flush();
        }

        $this->maybeAlert($portal, $run);

        return $run;
    }

    /**
     * Zapis z deduplikacją po (portal, zewnętrzne ID) — US-B3, US-B4.
     *
     * @param array<string, ScrapedOffer> $collected
     * @return array{0: int, 1: list<int>} [liczba nowych, ID wszystkich wykrytych]
     */
    private function store(Portal $portal, array $collected, bool $initialImport): array
    {
        $em = $this->em();
        $settings = $this->settings->get();
        $existing = $this->offers()->findByPortalAndExternalIds($portal, array_map('strval', array_keys($collected)));
        $now = new \DateTimeImmutable();
        $newCount = 0;
        $touched = [];

        foreach ($collected as $externalId => $scraped) {
            $offer = $existing[(string) $externalId] ?? null;
            $isNew = null === $offer;
            if ($isNew) {
                $offer = new JobOffer($portal, (string) $externalId, $now);
                $em->persist($offer);
            } else {
                $offer->markSeen($now);
            }
            $this->apply($offer, $scraped);

            if ($isNew) {
                ++$newCount;
                if ($this->blacklist->contains($offer->getCompany())) {
                    $offer->setStatus(ApplicationStatus::Hidden, $now);
                } elseif (!$initialImport && $this->matcher->passesNotificationRules($offer, $settings)) {
                    $offer->queueNotification();
                }
            }
            $touched[] = $offer;
        }
        $em->flush();

        return [$newCount, array_map(static fn (JobOffer $o) => (int) $o->getId(), $touched)];
    }

    private function apply(JobOffer $offer, ScrapedOffer $s): void
    {
        $offer->setUrl($s->url);
        $offer->setTitle('' !== trim($s->title) ? trim($s->title) : 'Bez tytułu');
        $offer->setCompany('' !== trim($s->company) ? trim($s->company) : 'brak');
        $offer->setLocation($s->location);
        $offer->setWorkMode($s->workMode);
        $offer->setSalary($s->salaryFrom, $s->salaryTo, $s->salaryCurrency);
        $offer->setContractType($s->contractType);
        $offer->setTags(array_values(array_filter(array_map('trim', $s->tags))));
        $offer->setSeniority($s->seniority);
        if (null !== $s->publishedAt) {
            $offer->setPublishedAt($s->publishedAt);
        }
        $offer->setDuplicateKey(Text::duplicateKey($offer->getCompany(), $offer->getTitle()));
    }

    /** @param list<int> $seenIds */
    private function expireMissing(Portal $portal, array $seenIds): void
    {
        foreach ($this->offers()->findNotSeen($portal, $seenIds) as $offer) {
            $offer->markMissed(max(1, $this->expireAfterMissedScans));
        }
    }

    private function maybeAlert(Portal $portal, ScanRun $run): void
    {
        if ($portal->getConsecutiveFailures() < max(1, $this->failureAlertThreshold) || null !== $portal->getFailureAlertSentAt()) {
            return;
        }
        $reason = ScanStatus::Failed === $run->getStatus() ? ($run->getError() ?? 'nieznany błąd') : 'Skan zwrócił 0 ogłoszeń.';
        $sent = $this->notifications->sendAlert(
            '⚠️ Problem ze skanowaniem: '.$portal->getName(),
            \sprintf("%d nieudanych skanów z rzędu.\nOstatni powód: %s\n\nAdapter może wymagać poprawki.", $portal->getConsecutiveFailures(), mb_substr($reason, 0, 1500)),
        );
        if ($sent) {
            $portal->markFailureAlertSent(new \DateTimeImmutable());
            $this->em()->flush();
        }
    }

    /** @return array{0: ScanRun, 1: Portal} */
    private function reloadAfterError(ScanRun $run, Portal $portal): array
    {
        if ($this->em()->isOpen()) {
            return [$run, $portal];
        }
        $this->doctrine->resetManager();
        $em = $this->em();

        return [$em->find(ScanRun::class, $run->getId()), $em->find(Portal::class, $portal->getId())];
    }

    private function em(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return $this->doctrine->getManager();
    }

    private function offers(): JobOfferRepository { return $this->em()->getRepository(JobOffer::class); }
    private function portals(): PortalRepository { return $this->em()->getRepository(Portal::class); }
    private function profiles(): SearchProfileRepository { return $this->em()->getRepository(\App\Entity\SearchProfile::class); }
}
