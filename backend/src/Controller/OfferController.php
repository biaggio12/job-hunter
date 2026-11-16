<?php

namespace App\Controller;

use App\Api\Presenter;
use App\Entity\JobOffer;
use App\Enum\ApplicationStatus;
use App\Enum\RecruitmentStage;
use App\Repository\JobOfferRepository;
use App\Repository\PortalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/offers')]
final class OfferController extends AbstractController
{
    public function __construct(
        private readonly JobOfferRepository $offers,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** US-C1, US-C2 */
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $q = $request->query;
        $status = $q->getString('status');
        if ('' !== $status && 'all' !== $status && null === ApplicationStatus::tryFrom($status)) {
            return $this->json(['error' => 'Nieprawidłowy status.'], 400);
        }
        $page = max(1, $q->getInt('page', 1));
        $perPage = min(100, max(5, $q->getInt('perPage', 25)));

        $result = $this->offers->search([
            'status' => $status,
            'portal' => $q->getString('portal'),
            'workMode' => $q->getString('workMode'),
            'location' => $q->getString('location'),
            'minSalary' => $q->getInt('minSalary') ?: null,
            'tech' => $q->getString('tech'),
            'q' => trim($q->getString('q')),
            'showExpired' => $q->getBoolean('showExpired'),
            'unseen' => $q->getBoolean('unseen'),
        ], $page, $perPage);

        $dups = $this->offers->duplicateInfo($result['items']);

        return $this->json([
            'items' => array_map(static fn (JobOffer $o) => Presenter::offer($o, $dups[$o->getId()] ?? null), $result['items']),
            'total' => $result['total'],
            'page' => $page,
            'perPage' => $perPage,
            'pages' => (int) max(1, ceil($result['total'] / $perPage)),
        ]);
    }

    /** Wartości do filtrów. */
    #[Route('/facets', methods: ['GET'])]
    public function facets(PortalRepository $portals): JsonResponse
    {
        return $this->json([
            'portals' => array_map(static fn ($p) => ['code' => $p->getCode(), 'name' => $p->getName()], $portals->findBy([], ['name' => 'ASC'])),
            'tags' => $this->offers->topTags(),
            'unseen' => $this->offers->countUnseen(),
        ]);
    }

    /** Kanban (US-D3). */
    #[Route('/pipeline', methods: ['GET'])]
    public function pipeline(): JsonResponse
    {
        $columns = [];
        foreach (RecruitmentStage::cases() as $stage) {
            $columns[$stage->value] = [];
        }
        foreach ($this->offers->findInPipeline() as $offer) {
            $columns[$offer->getStage()->value][] = Presenter::offer($offer);
        }

        return $this->json(['columns' => $columns]);
    }

    /** US-C3 */
    #[Route('/{id<\d+>}', methods: ['GET'])]
    public function show(JobOffer $offer): JsonResponse
    {
        return $this->json(Presenter::offerDetails($offer, $this->offers->findDuplicatesOf($offer)));
    }

    /** US-C4 — oznaczenie jako przejrzane po otwarciu. */
    #[Route('/{id<\d+>}/view', methods: ['POST'])]
    public function view(JobOffer $offer): JsonResponse
    {
        $offer->markViewed(new \DateTimeImmutable());
        $this->em->flush();

        return $this->json(Presenter::offer($offer));
    }

    /** US-D1 */
    #[Route('/{id<\d+>}/apply', methods: ['POST'])]
    public function apply(JobOffer $offer): JsonResponse
    {
        $offer->apply(new \DateTimeImmutable());
        $this->em->flush();

        return $this->details($offer);
    }

    /** US-D1 — cofnięcie. */
    #[Route('/{id<\d+>}/apply', methods: ['DELETE'])]
    public function unapply(JobOffer $offer): JsonResponse
    {
        $offer->unapply(new \DateTimeImmutable());
        $this->em->flush();

        return $this->details($offer);
    }

    /** Zmiana statusu (US-D2), etapu (US-D3), notatki (US-D4). */
    #[Route('/{id<\d+>}', methods: ['PATCH'])]
    public function update(JobOffer $offer, Request $request): JsonResponse
    {
        $data = $request->toArray();
        $now = new \DateTimeImmutable();

        if (\array_key_exists('status', $data)) {
            $status = ApplicationStatus::tryFrom((string) $data['status']);
            if (null === $status) {
                return $this->json(['error' => 'Nieprawidłowy status.'], 400);
            }
            $offer->setStatus($status, $now);
            $offer->markViewed($now);
        }
        if (\array_key_exists('stage', $data)) {
            $stage = null === $data['stage'] ? null : RecruitmentStage::tryFrom((string) $data['stage']);
            if (null !== $data['stage'] && null === $stage) {
                return $this->json(['error' => 'Nieprawidłowy etap.'], 400);
            }
            if (null === $stage) {
                $offer->unapply($now);
            } else {
                $offer->changeStage($stage, $now);
            }
        }
        if (\array_key_exists('note', $data)) {
            $note = null === $data['note'] ? null : (string) $data['note'];
            if (null !== $note && mb_strlen($note) > 20000) {
                return $this->json(['error' => 'Notatka jest za długa.'], 400);
            }
            $offer->setNote($note);
        }
        $this->em->flush();

        return $this->details($offer);
    }

    /** Oznacz wiele jako przejrzane (np. „oznacz wszystkie jako przejrzane”). */
    #[Route('/mark-all-viewed', methods: ['POST'])]
    public function markAllViewed(): JsonResponse
    {
        $count = $this->em->createQuery('UPDATE App\Entity\JobOffer o SET o.viewedAt = :now WHERE o.viewedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->execute();

        return $this->json(['updated' => $count]);
    }

    private function details(JobOffer $offer): JsonResponse
    {
        return $this->json(Presenter::offerDetails($offer, $this->offers->findDuplicatesOf($offer)));
    }
}
