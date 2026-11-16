<?php

namespace App\Tests\Unit;

use App\Portal\SalaryText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SalaryTextTest extends TestCase
{
    /** @return iterable<string, array{string, array{?float, ?float, ?string, ?string}}> */
    public static function cases(): iterable
    {
        yield 'pracuj monthly' => ['10 000–15 000 zł brutto / mies.', [10000.0, 15000.0, 'PLN', 'month']];
        yield 'non-breaking spaces' => ["12\u{00A0}500 – 18\u{00A0}000 zł netto (+ VAT) / mies.", [12500.0, 18000.0, 'PLN', 'month']];
        yield 'hourly' => ['120–150 zł netto (+ VAT) / godz.', [120.0, 150.0, 'PLN', 'hour']];
        yield 'single value eur' => ['5 000 EUR / mies.', [5000.0, 5000.0, 'EUR', 'month']];
        yield 'thousand separator' => ['12.000 - 16,000 PLN', [12000.0, 16000.0, 'PLN', 'month']];
        yield 'empty' => ['', [null, null, null, null]];
        yield 'no numbers' => ['do negocjacji', [null, null, null, null]];
    }

    /** @param array{?float, ?float, ?string, ?string} $expected */
    #[DataProvider('cases')]
    public function testParse(string $text, array $expected): void
    {
        self::assertSame($expected, SalaryText::parse($text));
    }
}
