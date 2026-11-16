<?php

namespace App\Controller;

use App\Api\Presenter;
use App\Entity\ScanRun;
use App\Enum\ScanTrigger;
use App\Message\ScanPortalMessage;
use App\Repository\PortalRepository;
use App\Repository\ScanRunRepository;
use App\Service\ScanService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/scans')]
final class ScanController extends AbstractController
{
    public function __construct(private readonly ScanRunRepository $scans)
    {
    }

    /** Historia skanów (US-F1). */
    #[Route('', methods: ['GET'])]
    public function list(Request $request, PortalRepository $portals): JsonResponse
    {
        $portal = null;
        if ('' !== ($code = $request->query->getString('portal'))) {
            $portal = $portals->findOneBy(['code' => $code]);
        }
        $limit = min(500, max(1, $request->query->getInt('limit', 100)));

        return $this->json([
            'items' => array_map(Presenter::scan(...), $this->scans->findRecent($limit, $portal)),
            'active' => $this->scans->countActive(),
        ]);
    }

    /** „Skanuj teraz” (US-B2) — skan wykonuje worker; UI odpytuje status po ID. */
    #[Route('', methods: ['POST'])]
    public function start(Request $request, ScanService $scanService, MessageBusInterface $bus): JsonResponse
    {
        $data = '' === $request->getContent() ? [] : $request->toArray();
        $portal = isset($data['portal']) && '' !== $data['portal'] ? (string) $data['portal'] : null;
        try {
            $runs = $scanService->createRuns($portal, ScanTrigger::Manual);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
        foreach ($runs as $run) {
            $bus->dispatch(new ScanPortalMessage((int) $run->getId()));
        }

        return $this->json(['items' => array_map(Presenter::scan(...), $runs)], 202);
    }

    /** Status wybranych skanów: /api/scans/status?ids=1,2,3 */
    #[Route('/status', methods: ['GET'])]
    public function status(Request $request): JsonResponse
    {
        $ids = array_values(array_filter(array_map('intval', explode(',', $request->query->getString('ids')))));
        $runs = [] === $ids ? [] : $this->scans->findBy(['id' => \array_slice($ids, 0, 50)]);
        $done = array_all($runs, static fn (ScanRun $r) => $r->getStatus()->isFinished());

        return $this->json([
            'items' => array_map(Presenter::scan(...), $runs),
            'finished' => $done,
            'newCount' => array_sum(array_map(static fn (ScanRun $r) => $r->getNewCount(), $runs)),
        ]);
    }
}
