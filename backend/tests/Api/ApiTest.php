<?php

namespace App\Tests\Api;

use App\Entity\JobOffer;
use App\Entity\Portal;
use App\Message\ScanPortalMessage;
use App\Util\Text;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/** Testy HTTP API na bazie testowej PostgreSQL (jobhunter_test). */
final class ApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->executeStatement(
            'TRUNCATE job_offer, stage_change, scan_run, portal, search_profile, settings, blacklisted_company RESTART IDENTITY CASCADE'
        );
    }

    /** @return array<mixed> */
    private function request(string $method, string $uri, ?array $json = null, int $expected = 200): array
    {
        $this->client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json'], content: null === $json ? null : json_encode($json));
        $response = $this->client->getResponse();
        self::assertSame($expected, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent() ?: 'null', true) ?? [];
    }

    private function seedOffer(Portal $portal, string $ext, string $company, string $title, ?int $salaryTo = null, array $tags = []): JobOffer
    {
        $o = new JobOffer($portal, $ext, new \DateTimeImmutable());
        $o->setUrl('https://example.com/'.$ext);
        $o->setTitle($title);
        $o->setCompany($company);
        $o->setWorkMode('remote');
        $o->setSalary(null, $salaryTo, null === $salaryTo ? null : 'PLN');
        $o->setTags($tags);
        $o->setDuplicateKey(Text::duplicateKey($company, $title));
        $this->em->persist($o);

        return $o;
    }

    public function testOfferLifecycle(): void
    {
        $jj = new Portal('justjoin', 'Just Join IT', 'https://justjoin.it');
        $nfj = new Portal('nofluffjobs', 'No Fluff Jobs', 'https://nofluffjobs.com');
        $this->em->persist($jj);
        $this->em->persist($nfj);
        $a = $this->seedOffer($jj, 'a', 'Acme Sp. z o.o.', 'Senior PHP Developer', 25000, ['PHP', 'Symfony']);
        $this->seedOffer($nfj, 'b', 'ACME', 'PHP Developer (k/m)', 20000, ['PHP']);
        $this->seedOffer($jj, 'c', 'Other', 'Python Dev', null, ['Python']);
        $this->em->flush();
        $id = $a->getId();

        // US-C1/C2: lista i filtry
        $list = $this->request('GET', '/api/offers');
        self::assertSame(3, $list['total']);
        self::assertSame(1, $this->request('GET', '/api/offers?tech=symfony')['total']);
        self::assertSame(2, $this->request('GET', '/api/offers?minSalary=20000')['total']);
        self::assertSame(1, $this->request('GET', '/api/offers?q=python')['total']);
        self::assertSame(2, $this->request('GET', '/api/offers?portal=justjoin')['total']);

        // US-B5: duplikat między portalami
        $item = array_values(array_filter($list['items'], static fn ($i) => $i['id'] === $id))[0];
        self::assertSame(1, $item['duplicates']['count']);

        // US-C4: przejrzane
        self::assertTrue($this->request('POST', "/api/offers/$id/view")['viewed']);

        // US-D1: zaaplikowano + cofnięcie
        $applied = $this->request('POST', "/api/offers/$id/apply");
        self::assertSame('applied', $applied['status']);
        self::assertSame('applied', $applied['stage']);
        self::assertNotNull($applied['appliedAt']);
        $dup = $this->request('GET', '/api/offers?portal=nofluffjobs')['items'][0];
        self::assertTrue($dup['duplicates']['applied'], 'ostrzeżenie przy duplikacie');
        self::assertSame('none', $this->request('DELETE', "/api/offers/$id/apply")['status']);

        // US-D3 + US-D4: etap, historia, notatka
        $this->request('PATCH', "/api/offers/$id", ['stage' => 'interview', 'note' => 'Rozmowa z HR, podałem 25k']);
        $details = $this->request('GET', "/api/offers/$id");
        self::assertSame('interview', $details['stage']);
        self::assertSame('Rozmowa z HR, podałem 25k', $details['note']);
        self::assertSame('interview', end($details['stageHistory'])['to']);
        $pipeline = $this->request('GET', '/api/offers/pipeline');
        self::assertCount(1, $pipeline['columns']['interview']);

        // US-D2: odrzucone znika z listy domyślnej
        $this->request('PATCH', "/api/offers/$id", ['status' => 'rejected']);
        self::assertSame(2, $this->request('GET', '/api/offers')['total']);
        self::assertSame(1, $this->request('GET', '/api/offers?status=rejected')['total']);

        $this->request('PATCH', "/api/offers/$id", ['status' => 'bogus'], 400);
    }

    public function testBlacklistHidesCompanyOffers(): void
    {
        $p = new Portal('justjoin', 'Just Join IT', 'https://justjoin.it');
        $this->em->persist($p);
        $this->seedOffer($p, 'x', 'Evil Corp S.A.', 'PHP Dev');
        $this->em->flush();

        $res = $this->request('POST', '/api/blacklist', ['name' => 'evil corp'], 201);
        self::assertSame(1, $res['hiddenOffers']);
        self::assertSame(0, $this->request('GET', '/api/offers')['total']);
        self::assertSame(1, $this->request('GET', '/api/offers?status=hidden')['total']);
        self::assertCount(1, $this->request('GET', '/api/blacklist'));
    }

    public function testProfilesCrudAndValidation(): void
    {
        $created = $this->request('POST', '/api/profiles', [
            'name' => 'Symfony Wrocław', 'keywords' => ['symfony'], 'workModes' => ['hybrid'], 'seniorities' => ['senior'], 'locations' => ['Wrocław'],
        ], 201);
        self::assertSame(['Wrocław'], $created['locations']);

        $this->request('PUT', '/api/profiles/'.$created['id'], ['name' => 'Zmieniony', 'keywords' => ['php'], 'active' => false]);
        self::assertFalse($this->request('GET', '/api/profiles')[0]['active']);

        $this->request('POST', '/api/profiles', ['name' => '', 'workModes' => ['mars']], 422);
        $this->request('DELETE', '/api/profiles/'.$created['id'], null, 204);
    }

    public function testSettingsMaskSecretsAndValidate(): void
    {
        $saved = $this->request('PUT', '/api/settings', [
            'discordWebhookUrl' => 'https://discord.com/api/webhooks/123/abc-DEF_xyz',
            'quietHoursStart' => '22:00', 'quietHoursEnd' => '07:00', 'notifyMinSalary' => 18000, 'notifyKeywords' => ['symfony', ' '],
        ]);
        self::assertSame('••••_xyz', $saved['discordWebhookUrl']);
        self::assertSame(['symfony'], $saved['notifyKeywords']);
        self::assertTrue($saved['channels']['discord']['configured']);

        // Puste pole sekretu nie kasuje zapisanej wartości
        $again = $this->request('PUT', '/api/settings', ['discordWebhookUrl' => '']);
        self::assertSame('••••_xyz', $again['discordWebhookUrl']);

        // Jawne usunięcie sekretu
        $cleared = $this->request('PUT', '/api/settings', ['clearSecrets' => ['discordWebhookUrl']]);
        self::assertNull($cleared['discordWebhookUrl']);

        $this->request('PUT', '/api/settings', ['discordWebhookUrl' => 'https://evil.example.com/hook'], 422);
        $this->request('PUT', '/api/settings', ['quietHoursStart' => '25:00'], 422);
    }

    public function testManualScanIsQueuedForWorker(): void
    {
        $p = new Portal('justjoin', 'Just Join IT', 'https://justjoin.it');
        $this->em->persist($p);
        $this->em->flush();

        $res = $this->request('POST', '/api/scans', ['portal' => 'justjoin'], 202);
        self::assertSame('queued', $res['items'][0]['status']);

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $sent = $transport->getSent();
        self::assertCount(1, $sent);
        self::assertInstanceOf(ScanPortalMessage::class, $sent[0]->getMessage());

        $status = $this->request('GET', '/api/scans/status?ids='.$res['items'][0]['id']);
        self::assertFalse($status['finished']);
        $this->request('POST', '/api/scans', ['portal' => 'nieznany'], 400);
    }

    public function testPortalToggle(): void
    {
        $p = new Portal('justjoin', 'Just Join IT', 'https://justjoin.it');
        $this->em->persist($p);
        $this->em->flush();

        $res = $this->request('PATCH', '/api/portals/'.$p->getId(), ['enabled' => false]);
        self::assertFalse($res['enabled']);
        self::assertFalse($this->request('GET', '/api/portals')[0]['enabled']);
    }
}
