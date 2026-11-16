<?php

namespace App\Tests\Unit;

use App\Entity\Settings;
use App\Service\SettingsProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QuietHoursTest extends TestCase
{
    private function provider(?string $start, ?string $end): SettingsProvider
    {
        $settings = new Settings();
        $settings->quietHoursStart = $start;
        $settings->quietHoursEnd = $end;
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturn($settings);

        return new SettingsProvider($em, '', '', '', 'Europe/Warsaw');
    }

    /** @return iterable<array{?string, ?string, string, bool}> */
    public static function cases(): iterable
    {
        yield 'disabled' => [null, null, '03:00', false];
        yield 'overnight inside late' => ['22:00', '07:00', '23:30', true];
        yield 'overnight inside early' => ['22:00', '07:00', '06:59', true];
        yield 'overnight outside' => ['22:00', '07:00', '07:00', false];
        yield 'same day inside' => ['13:00', '14:00', '13:30', true];
        yield 'same day outside' => ['13:00', '14:00', '15:00', false];
    }

    #[DataProvider('cases')]
    public function testIsQuietTime(?string $start, ?string $end, string $time, bool $expected): void
    {
        $now = new \DateTimeImmutable('2026-11-15 '.$time, new \DateTimeZone('Europe/Warsaw'));
        self::assertSame($expected, $this->provider($start, $end)->isQuietTime($now));
    }
}
