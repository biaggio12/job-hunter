<?php

namespace App\Tests\Unit;

use App\Entity\JobOffer;
use App\Entity\Portal;
use App\Enum\ApplicationStatus;
use App\Enum\RecruitmentStage;
use PHPUnit\Framework\TestCase;

final class JobOfferTest extends TestCase
{
    private function offer(): JobOffer
    {
        return new JobOffer(new Portal('test', 'Test', 'https://example.com'), 'ext-1', new \DateTimeImmutable('2026-11-01 10:00'));
    }

    public function testApplyRecordsDateStageAndHistory(): void
    {
        $offer = $this->offer();
        $at = new \DateTimeImmutable('2026-11-02 12:00');
        $offer->apply($at);

        self::assertSame(ApplicationStatus::Applied, $offer->getStatus());
        self::assertSame($at, $offer->getAppliedAt());
        self::assertSame(RecruitmentStage::Applied, $offer->getStage());
        self::assertNotNull($offer->getViewedAt());
        self::assertCount(1, $offer->getStageHistory());
    }

    public function testUnapplyRevertsEverything(): void
    {
        $offer = $this->offer();
        $offer->apply(new \DateTimeImmutable());
        $offer->unapply(new \DateTimeImmutable());

        self::assertSame(ApplicationStatus::None, $offer->getStatus());
        self::assertNull($offer->getAppliedAt());
        self::assertNull($offer->getStage());
        self::assertCount(2, $offer->getStageHistory());
    }

    public function testStageChangesAreRecordedInOrder(): void
    {
        $offer = $this->offer();
        $offer->changeStage(RecruitmentStage::Interview, new \DateTimeImmutable('2026-11-03'));
        $offer->changeStage(RecruitmentStage::Offer, new \DateTimeImmutable('2026-11-10'));
        $offer->changeStage(RecruitmentStage::Offer, new \DateTimeImmutable('2026-11-11')); // bez zmiany

        self::assertSame(ApplicationStatus::Applied, $offer->getStatus(), 'etap implikuje aplikację');
        $history = $offer->getStageHistory()->toArray();
        self::assertCount(2, $history);
        self::assertNull($history[0]->getFromStage());
        self::assertSame(RecruitmentStage::Interview, $history[0]->getToStage());
        self::assertSame(RecruitmentStage::Offer, $history[1]->getToStage());
    }

    public function testRejectingClearsPendingNotification(): void
    {
        $offer = $this->offer();
        $offer->queueNotification();
        self::assertTrue($offer->isNotificationPending());

        $offer->setStatus(ApplicationStatus::Rejected, new \DateTimeImmutable());
        self::assertFalse($offer->isNotificationPending());
    }

    public function testNotificationIsQueuedOnlyOnce(): void
    {
        $offer = $this->offer();
        $offer->queueNotification();
        $offer->markNotified(new \DateTimeImmutable());
        $offer->queueNotification();

        self::assertFalse($offer->isNotificationPending());
    }

    public function testExpiresAfterConfiguredMissedScansAndRevivesWhenSeen(): void
    {
        $offer = $this->offer();
        $offer->markMissed(3);
        $offer->markMissed(3);
        self::assertFalse($offer->isExpired());
        $offer->markMissed(3);
        self::assertTrue($offer->isExpired());

        $offer->markSeen(new \DateTimeImmutable());
        self::assertFalse($offer->isExpired());
        self::assertSame(0, $offer->getMissedScans());
    }
}
