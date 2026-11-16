<?php

namespace App\Controller;

use App\Api\Presenter;
use App\Dto\SettingsInput;
use App\Entity\BlacklistedCompany;
use App\Notification\NotificationDispatcher;
use App\Repository\BlacklistedCompanyRepository;
use App\Service\Blacklist;
use App\Service\SettingsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class SettingsController extends AbstractController
{
    public function __construct(
        private readonly SettingsProvider $settings,
        private readonly NotificationDispatcher $notifications,
        #[Autowire('%env(int:SCAN_INTERVAL_HOURS)%')]
        private readonly int $scanIntervalHours,
    ) {
    }

    #[Route('/settings', methods: ['GET'])]
    public function show(): JsonResponse
    {
        return $this->json($this->present());
    }

    #[Route('/settings', methods: ['PUT'])]
    public function update(#[MapRequestPayload] SettingsInput $input, EntityManagerInterface $em): JsonResponse
    {
        $input->applyTo($this->settings->get());
        $em->flush();

        return $this->json($this->present());
    }

    /** US-E5 */
    #[Route('/settings/test-notification', methods: ['POST'])]
    public function test(Request $request): JsonResponse
    {
        $channel = (string) ($request->toArray()['channel'] ?? '');
        try {
            $this->notifications->sendTest($channel);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }

        return $this->json(['ok' => true]);
    }

    /** US-D5 */
    #[Route('/blacklist', methods: ['GET'])]
    public function blacklist(BlacklistedCompanyRepository $repo): JsonResponse
    {
        return $this->json(array_map(Presenter::blacklisted(...), $repo->findBy([], ['name' => 'ASC'])));
    }

    #[Route('/blacklist', methods: ['POST'])]
    public function addToBlacklist(Request $request, Blacklist $blacklist): JsonResponse
    {
        $name = trim((string) ($request->toArray()['name'] ?? ''));
        if ('' === $name || mb_strlen($name) > 255) {
            return $this->json(['error' => 'Podaj nazwę firmy.'], 400);
        }
        try {
            $result = $blacklist->add($name);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json(Presenter::blacklisted($result['entry']) + ['hiddenOffers' => $result['hidden']], 201);
    }

    #[Route('/blacklist/{id<\d+>}', methods: ['DELETE'])]
    public function removeFromBlacklist(BlacklistedCompany $entry, Blacklist $blacklist): JsonResponse
    {
        $blacklist->remove($entry);

        return $this->json(null, 204);
    }

    private function present(): array
    {
        $s = $this->settings->get();
        $channels = [];
        foreach ($this->notifications->channels() as $code => $channel) {
            $channels[$code] = ['name' => $channel->getName(), 'configured' => $channel->isConfigured(), 'enabled' => $channel->isEnabled()];
        }

        return [
            'discordEnabled' => $s->discordEnabled,
            // Sekrety nie są odsyłane w całości — tylko informacja, czy są ustawione.
            'discordWebhookUrl' => self::mask($s->discordWebhookUrl),
            'telegramEnabled' => $s->telegramEnabled,
            'telegramBotToken' => self::mask($s->telegramBotToken),
            'telegramChatId' => $s->telegramChatId,
            'notifyMinSalary' => $s->notifyMinSalary,
            'notifyIncludeNoSalary' => $s->notifyIncludeNoSalary,
            'notifyRemoteOnly' => $s->notifyRemoteOnly,
            'notifyKeywords' => $s->notifyKeywords,
            'quietHoursStart' => $s->quietHoursStart,
            'quietHoursEnd' => $s->quietHoursEnd,
            'env' => [
                'discordWebhookUrl' => $this->settings->envProvides('discordWebhookUrl'),
                'telegramBotToken' => $this->settings->envProvides('telegramBotToken'),
                'telegramChatId' => $this->settings->envProvides('telegramChatId'),
            ],
            'channels' => $channels,
            'scanIntervalHours' => $this->scanIntervalHours,
            'timezone' => $this->settings->timezone()->getName(),
            'quietNow' => $this->settings->isQuietTime(),
        ];
    }

    private static function mask(?string $secret): ?string
    {
        if (null === $secret || '' === $secret) {
            return null;
        }

        return '••••'.mb_substr($secret, -4);
    }
}
