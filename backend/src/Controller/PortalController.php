<?php

namespace App\Controller;

use App\Api\Presenter;
use App\Entity\Portal;
use App\Portal\PortalAdapterRegistry;
use App\Repository\PortalRepository;
use App\Repository\ScanRunRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/portals')]
final class PortalController extends AbstractController
{
    public function __construct(
        private readonly ScanRunRepository $scans,
        private readonly PortalAdapterRegistry $registry,
    ) {
    }

    /** US-A1 */
    #[Route('', methods: ['GET'])]
    public function list(PortalRepository $portals): JsonResponse
    {
        return $this->json(array_map(
            fn (Portal $p) => $this->present($p),
            $portals->findBy([], ['name' => 'ASC']),
        ));
    }

    /** US-A2 */
    #[Route('/{id<\d+>}', methods: ['PATCH'])]
    public function update(Portal $portal, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = $request->toArray();
        if (\array_key_exists('enabled', $data)) {
            $portal->setEnabled((bool) $data['enabled']);
        }
        $em->flush();

        return $this->json($this->present($portal));
    }

    private function present(Portal $p): array
    {
        return Presenter::portal($p, $this->scans->findRecent(1, $p)[0] ?? null, $this->registry->has($p->getCode()));
    }
}
