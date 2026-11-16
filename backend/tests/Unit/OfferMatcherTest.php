<?php

namespace App\Tests\Unit;

use App\Entity\JobOffer;
use App\Entity\Portal;
use App\Entity\SearchProfile;
use App\Entity\Settings;
use App\Portal\ScrapedOffer;
use App\Service\OfferMatcher;
use PHPUnit\Framework\TestCase;

final class OfferMatcherTest extends TestCase
{
    private OfferMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new OfferMatcher();
    }

    private static function scraped(?string $workMode = 'remote', ?string $seniority = 'senior', ?string $location = 'Warszawa'): ScrapedOffer
    {
        return new ScrapedOffer('1', 'https://x', 'PHP Dev', 'Acme', $location, $workMode, seniority: $seniority);
    }

    private static function offer(?int $from, ?int $to, string $workMode = 'remote', string $title = 'PHP Developer', array $tags = []): JobOffer
    {
        $o = new JobOffer(new Portal('t', 'T', 'https://t'), '1', new \DateTimeImmutable());
        $o->setTitle($title);
        $o->setCompany('Acme');
        $o->setUrl('https://x');
        $o->setWorkMode($workMode);
        $o->setSalary($from, $to, 'PLN');
        $o->setTags($tags);

        return $o;
    }

    public function testProfileFiltersWorkModeAndSeniority(): void
    {
        $p = new SearchProfile('p');
        $p->setWorkModes(['remote']);
        $p->setSeniorities(['mid', 'senior']);

        self::assertTrue($this->matcher->matchesProfile(self::scraped(), $p));
        self::assertFalse($this->matcher->matchesProfile(self::scraped('office'), $p));
        self::assertFalse($this->matcher->matchesProfile(self::scraped('remote', 'junior'), $p));
        self::assertTrue($this->matcher->matchesProfile(self::scraped(null, null), $p), 'brak danych nie wyklucza');
    }

    public function testProfileKeywordsMustAppearInTitleOrTags(): void
    {
        $p = new SearchProfile('p');
        $p->setKeywords(['php', 'symfony']);

        $sap = new ScrapedOffer('1', 'https://x', 'Konsultant SAP PP', 'Acme', tags: ['SAP PP', 'SAP HANA']);
        $byTag = new ScrapedOffer('2', 'https://x', 'Backend Engineer', 'Acme', tags: ['Symfony', 'MySQL']);
        $byTitle = new ScrapedOffer('3', 'https://x', 'Programista/-ka PHP', 'Acme');

        self::assertFalse($this->matcher->matchesProfile($sap, $p));
        self::assertTrue($this->matcher->matchesProfile($byTag, $p));
        self::assertTrue($this->matcher->matchesProfile($byTitle, $p));
    }

    public function testProfileLocationsIgnoreRemoteOffers(): void
    {
        $p = new SearchProfile('p');
        $p->setLocations(['Wrocław']);

        self::assertTrue($this->matcher->matchesProfile(self::scraped('hybrid', null, 'Wroclaw, Poznań'), $p));
        self::assertFalse($this->matcher->matchesProfile(self::scraped('hybrid', null, 'Kraków'), $p));
        self::assertTrue($this->matcher->matchesProfile(self::scraped('remote', null, 'Kraków'), $p));
    }

    public function testNoRulesNotifiesEverything(): void
    {
        self::assertTrue($this->matcher->passesNotificationRules(self::offer(null, null, 'office'), new Settings()));
    }

    public function testMinSalaryUsesUpperBound(): void
    {
        $s = new Settings();
        $s->notifyMinSalary = 20000;

        self::assertTrue($this->matcher->passesNotificationRules(self::offer(15000, 22000), $s));
        self::assertFalse($this->matcher->passesNotificationRules(self::offer(15000, 18000), $s));
        self::assertTrue($this->matcher->passesNotificationRules(self::offer(null, null), $s));

        $s->notifyIncludeNoSalary = false;
        self::assertFalse($this->matcher->passesNotificationRules(self::offer(null, null), $s));
    }

    public function testRemoteOnlyAndKeywords(): void
    {
        $s = new Settings();
        $s->notifyRemoteOnly = true;
        $s->notifyKeywords = ['symfony'];

        self::assertFalse($this->matcher->passesNotificationRules(self::offer(null, null, 'hybrid', 'Symfony Dev'), $s));
        self::assertTrue($this->matcher->passesNotificationRules(self::offer(null, null, 'remote', 'PHP Dev', ['Symfony']), $s));
        self::assertFalse($this->matcher->passesNotificationRules(self::offer(null, null, 'remote', 'PHP Dev', ['Laravel']), $s));
    }
}
