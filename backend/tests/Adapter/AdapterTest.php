<?php

namespace App\Tests\Adapter;

use App\Entity\SearchProfile;
use App\Portal\Adapter\BulldogJobAdapter;
use App\Portal\Adapter\JustJoinItAdapter;
use App\Portal\Adapter\NoFluffJobsAdapter;
use App\Portal\Adapter\PracujAdapter;
use App\Portal\Adapter\TheProtocolAdapter;
use App\Portal\PoliteHttpClient;
use App\Portal\PortalException;
use App\Portal\ScrapedOffer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Testy adapterów na przyciętych, prawdziwych odpowiedziach portali (tests/fixtures).
 * Gdy portal zmieni format, nagraj nowy fixture i popraw mapowanie.
 */
final class AdapterTest extends TestCase
{
    /** @var list<array{method: string, url: string, body: ?string}> */
    private array $requests = [];

    private function client(string ...$bodies): PoliteHttpClient
    {
        $responses = array_map(static fn (string $b) => new MockResponse($b, ['http_code' => 200]), $bodies);
        $mock = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses) {
            $this->requests[] = ['method' => $method, 'url' => $url, 'body' => $options['body'] ?? null];

            return array_shift($responses) ?? new MockResponse('{}', ['http_code' => 500]);
        });

        return new PoliteHttpClient($mock, new NullLogger(), 0, 3, 'test-agent');
    }

    private static function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__.'/../fixtures/'.$name);
    }

    private static function profile(): SearchProfile
    {
        $p = new SearchProfile('test');
        $p->setKeywords(['symfony']);
        $p->setWorkModes(['remote']);
        $p->setSeniorities(['mid', 'senior']);

        return $p;
    }

    /** @return list<ScrapedOffer> */
    private static function collect(iterable $offers): array
    {
        return [...$offers];
    }

    public function testJustJoinIt(): void
    {
        $offers = self::collect((new JustJoinItAdapter($this->client(self::fixture('justjoin.json'))))->fetch(self::profile()));

        self::assertCount(3, $offers);
        $o = $offers[0];
        self::assertSame('1fc1bed2-369a-4102-afc8-f3bdeb3c40ff', $o->externalId);
        self::assertSame('https://justjoin.it/job-offer/ideamotive-senior-php-developer-k-m-e-commerce-saas-warszawa-php', $o->url);
        self::assertSame('Ideamotive', $o->company);
        self::assertSame('remote', $o->workMode);
        self::assertSame('senior', $o->seniority);
        self::assertSame([20160, 26880, 'PLN', 'b2b'], [$o->salaryFrom, $o->salaryTo, $o->salaryCurrency, $o->contractType]);
        self::assertContains('PHP', $o->tags);
        self::assertSame('2026-11-15', $o->publishedAt?->format('Y-m-d'));

        $url = $this->requests[0]['url'];
        self::assertStringContainsString('keywords=symfony', $url);
        self::assertStringContainsString('experienceLevels=mid&experienceLevels=senior', $url);
        self::assertCount(1, $this->requests, 'brak kursora → koniec paginacji');
    }

    public function testNoFluffJobs(): void
    {
        $offers = self::collect((new NoFluffJobsAdapter($this->client(self::fixture('nofluffjobs.json'))))->fetch(self::profile()));

        self::assertCount(2, $offers);
        $o = $offers[0];
        self::assertSame('senior-php-software-engineer-symfony-spyrosoft-Remote', $o->externalId);
        self::assertSame('https://nofluffjobs.com/pl/job/senior-php-software-engineer-symfony-spyrosoft-remote', $o->url);
        self::assertSame('Spyrosoft', $o->company);
        self::assertSame('remote', $o->workMode);
        self::assertSame('senior', $o->seniority);
        self::assertSame([16800, 22680, 'PLN', 'b2b'], [$o->salaryFrom, $o->salaryTo, $o->salaryCurrency, $o->contractType]);
        self::assertStringContainsString('Wrocław', (string) $o->location);

        $body = json_decode((string) $this->requests[0]['body'], true);
        self::assertSame(['symfony'], $body['criteriaSearch']['keyword']);
        self::assertSame(['remote'], $body['criteriaSearch']['city']);
        self::assertSame(['mid', 'senior'], $body['criteriaSearch']['seniority']);
    }

    public function testBulldogJob(): void
    {
        $offers = self::collect((new BulldogJobAdapter($this->client(self::fixture('bulldogjob.html'))))->fetch(self::profile()));

        self::assertCount(3, $offers);
        $o = $offers[0];
        self::assertSame('https://bulldogjob.pl/companies/jobs/261456-php-developer-gdansk-teamquest', $o->url);
        self::assertSame('TeamQuest', $o->company);
        self::assertSame([12000, 16000, 'PLN'], [$o->salaryFrom, $o->salaryTo, $o->salaryCurrency]);
        self::assertSame('junior', $o->seniority);
        self::assertSame('office', $o->workMode);
        self::assertNull($offers[2]->salaryFrom, 'brak widełek');
        self::assertSame('mid', $offers[2]->seniority, '"medium" → mid');

        self::assertSame('https://bulldogjob.pl/companies/jobs/s/skills,symfony/experienceLevel,medium,senior', $this->requests[0]['url']);
    }

    public function testPracuj(): void
    {
        $offers = self::collect((new PracujAdapter($this->client(self::fixture('pracuj.html'))))->fetch(self::profile()));

        self::assertCount(3, $offers);
        self::assertSame('Programista/-ka PHP', $offers[0]->title);
        self::assertSame('https://www.pracuj.pl/praca/programista-ka-php-gdansk,oferta,1005213121', $offers[0]->url);
        self::assertSame('remote', $offers[0]->workMode);
        self::assertSame('mid', $offers[0]->seniority);
        self::assertSame('b2b', $offers[0]->contractType);
        self::assertNull($offers[0]->salaryFrom);

        // 90–125 zł/godz. → przeliczone na miesiąc (168 h)
        self::assertSame([90 * 168, 125 * 168, 'PLN'], [$offers[1]->salaryFrom, $offers[1]->salaryTo, $offers[1]->salaryCurrency]);
        self::assertSame('remote', $offers[2]->workMode, 'zdalna lub hybrydowa → zdalna');
        self::assertSame('senior', $offers[2]->seniority);

        self::assertSame('https://it.pracuj.pl/praca/symfony;kw?wm=home-office&et=4%2C18', $this->requests[0]['url']);
    }

    public function testCloudflareChallengeIsReportedNotBypassed(): void
    {
        $challenge = new MockResponse('<html><title>Just a moment...</title></html>', ['http_code' => 403]);
        $client = new PoliteHttpClient(new MockHttpClient($challenge), new NullLogger(), 0, 3, 'test-agent');

        $this->expectException(PortalException::class);
        $this->expectExceptionMessageMatches('/blokuje automatyczne pobieranie/');
        self::collect((new TheProtocolAdapter($client))->fetch(self::profile()));
    }

    public function testChangedStructureGivesReadableError(): void
    {
        $this->expectException(PortalException::class);
        $this->expectExceptionMessageMatches('/zmienić strukturę/');
        self::collect((new BulldogJobAdapter($this->client('<html>nowy layout</html>')))->fetch(self::profile()));
    }
}
