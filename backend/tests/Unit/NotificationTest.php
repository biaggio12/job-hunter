<?php

namespace App\Tests\Unit;

use App\Entity\JobOffer;
use App\Entity\Portal;
use App\Entity\Settings;
use App\Notification\DiscordChannel;
use App\Notification\NotificationDispatcher;
use App\Notification\NotificationException;
use App\Notification\OfferFormatter;
use App\Notification\TelegramChannel;
use App\Repository\JobOfferRepository;
use App\Service\SettingsProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class NotificationTest extends TestCase
{
    private function settings(): SettingsProvider
    {
        $s = new Settings();
        $s->discordWebhookUrl = 'https://discord.com/api/webhooks/1/abc';
        $s->telegramEnabled = true;
        $s->telegramBotToken = '123:abc';
        $s->telegramChatId = '-100';
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturn($s);

        return new SettingsProvider($em, '', '', '', 'Europe/Warsaw');
    }

    /** @return list<JobOffer> */
    private static function offers(int $n): array
    {
        $portal = new Portal('justjoin', 'Just Join IT', 'https://justjoin.it');
        $out = [];
        for ($i = 1; $i <= $n; ++$i) {
            $o = new JobOffer($portal, (string) $i, new \DateTimeImmutable());
            $o->setTitle("PHP Developer <$i>");
            $o->setCompany('Acme & Co');
            $o->setUrl("https://justjoin.it/job-offer/$i");
            $o->setWorkMode('remote');
            $o->setSalary(20000, 25000, 'PLN');
            $o->setContractType('b2b');
            $out[] = $o;
        }

        return $out;
    }

    public function testDiscordGroupsOffersIntoMessagesOfTenEmbeds(): void
    {
        $payloads = [];
        $http = new MockHttpClient(function ($method, $url, $options) use (&$payloads) {
            $payloads[] = json_decode($options['body'], true);

            return new MockResponse('', ['http_code' => 204]);
        });
        $channel = new DiscordChannel($http, $this->settings(), new OfferFormatter('http://localhost:8088'), new NullLogger());

        $channel->sendOffers(self::offers(23));

        self::assertCount(3, $payloads);
        self::assertSame([10, 10, 3], array_map(static fn ($p) => \count($p['embeds']), $payloads));
        self::assertStringContainsString('23 nowe ogłoszenia', $payloads[0]['content']);
        $embed = $payloads[0]['embeds'][0];
        self::assertSame('PHP Developer <1>', $embed['title']);
        self::assertSame('https://justjoin.it/job-offer/1', $embed['url']);
        self::assertStringContainsString('20 000 – 25 000 PLN/mies. (B2B)', $embed['description']);
        self::assertSame('Just Join IT', $embed['footer']['text']);
    }

    public function testDiscordRetriesAfterRateLimit(): void
    {
        $responses = [
            new MockResponse('{"retry_after": 0.01}', ['http_code' => 429]),
            new MockResponse('', ['http_code' => 204]),
        ];
        $http = new MockHttpClient($responses);
        $channel = new DiscordChannel($http, $this->settings(), new OfferFormatter(''), new NullLogger());

        $channel->sendMessage('t', 'x');
        self::assertSame(2, $http->getRequestsCount());
    }

    public function testDiscordErrorBecomesNotificationException(): void
    {
        $http = new MockHttpClient(new MockResponse('{"message":"Unknown Webhook"}', ['http_code' => 404]));
        $channel = new DiscordChannel($http, $this->settings(), new OfferFormatter(''), new NullLogger());

        $this->expectException(NotificationException::class);
        $channel->sendMessage('t', 'x');
    }

    public function testTelegramEscapesHtml(): void
    {
        $body = null;
        $http = new MockHttpClient(function ($method, $url, $options) use (&$body) {
            $body = json_decode($options['body'], true);

            return new MockResponse('{"ok":true}', ['http_code' => 200]);
        });
        (new TelegramChannel($http, $this->settings(), new OfferFormatter('')))->sendOffers(self::offers(1));

        self::assertSame('HTML', $body['parse_mode']);
        self::assertSame('-100', $body['chat_id']);
        self::assertStringContainsString('PHP Developer &lt;1&gt;', $body['text']);
        self::assertStringContainsString('Acme &amp; Co', $body['text']);
    }

    public function testDispatcherSendsPendingOnceAndMarksNotified(): void
    {
        $offers = self::offers(2);
        foreach ($offers as $o) {
            $o->queueNotification();
        }
        $repo = $this->createStub(JobOfferRepository::class);
        $repo->method('findPendingNotifications')->willReturn($offers);
        $http = new MockHttpClient(static fn () => new MockResponse('{"ok":true}', ['http_code' => 200]));
        $settings = $this->settings();
        $channels = [
            new DiscordChannel($http, $settings, new OfferFormatter(''), new NullLogger()),
            new TelegramChannel($http, $settings, new OfferFormatter('')),
        ];
        $dispatcher = new NotificationDispatcher($channels, $repo, $settings, $this->createStub(EntityManagerInterface::class), new NullLogger());

        self::assertSame(2, $dispatcher->flushPending());
        self::assertSame(2, $http->getRequestsCount(), 'jedna wiadomość na kanał');
        foreach ($offers as $o) {
            self::assertFalse($o->isNotificationPending());
            self::assertNotNull($o->getNotifiedAt());
        }
    }

    public function testDispatcherKeepsPendingWhenAllChannelsFail(): void
    {
        $offers = self::offers(1);
        $offers[0]->queueNotification();
        $repo = $this->createStub(JobOfferRepository::class);
        $repo->method('findPendingNotifications')->willReturn($offers);
        $http = new MockHttpClient(static fn () => new MockResponse('err', ['http_code' => 500]));
        $settings = $this->settings();
        $dispatcher = new NotificationDispatcher(
            [new DiscordChannel($http, $settings, new OfferFormatter(''), new NullLogger())],
            $repo, $settings, $this->createStub(EntityManagerInterface::class), new NullLogger(),
        );

        self::assertSame(0, $dispatcher->flushPending());
        self::assertTrue($offers[0]->isNotificationPending(), 'spróbujemy ponownie przy kolejnym wywołaniu');
    }
}
