<?php

namespace App\Controller;

use App\Api\Presenter;
use App\Dto\ProfileInput;
use App\Entity\SearchProfile;
use App\Repository\SearchProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/** Profile wyszukiwania (US-A3). Zmiany działają od następnego skanu. */
#[Route('/api/profiles')]
final class ProfileController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('', methods: ['GET'])]
    public function list(SearchProfileRepository $profiles): JsonResponse
    {
        return $this->json(array_map(Presenter::profile(...), $profiles->findBy([], ['id' => 'ASC'])));
    }

    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] ProfileInput $input): JsonResponse
    {
        $profile = new SearchProfile($input->name);
        $input->applyTo($profile);
        $this->em->persist($profile);
        $this->em->flush();

        return $this->json(Presenter::profile($profile), 201);
    }

    #[Route('/{id<\d+>}', methods: ['PUT'])]
    public function update(SearchProfile $profile, #[MapRequestPayload] ProfileInput $input): JsonResponse
    {
        $input->applyTo($profile);
        $this->em->flush();

        return $this->json(Presenter::profile($profile));
    }

    #[Route('/{id<\d+>}', methods: ['DELETE'])]
    public function delete(SearchProfile $profile): JsonResponse
    {
        $this->em->remove($profile);
        $this->em->flush();

        return $this->json(null, 204);
    }
}
