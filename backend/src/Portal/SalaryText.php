<?php

namespace App\Portal;

/** Parser tekstowych widełek, np. „10 000–15 000 zł brutto / mies.” lub „120-150 zł/godz.”. */
final class SalaryText
{
    /** @return array{0: ?float, 1: ?float, 2: ?string, 3: ?string} [od, do, waluta, okres] */
    public static function parse(string $text): array
    {
        if ('' === trim($text) || !preg_match_all('/\d[\d\s\x{00A0}.,]*/u', $text, $m)) {
            return [null, null, null, null];
        }
        $nums = [];
        foreach ($m[0] as $raw) {
            $clean = preg_replace('/[\s\x{00A0}]/u', '', $raw) ?? '';
            $clean = rtrim($clean, '.,');
            // „12,5” → 12.5; „12.000” / „12,000” (separator tysięcy) → 12000
            if (preg_match('/^\d{1,3}([.,]\d{3})+$/', $clean)) {
                $clean = preg_replace('/[.,]/', '', $clean) ?? $clean;
            } else {
                $clean = str_replace(',', '.', $clean);
            }
            if (is_numeric($clean)) {
                $nums[] = (float) $clean;
            }
        }
        if ([] === $nums) {
            return [null, null, null, null];
        }

        $t = mb_strtolower($text);
        $currency = match (true) {
            str_contains($t, 'eur') || str_contains($t, '€') => 'EUR',
            str_contains($t, 'usd') || str_contains($t, '$') => 'USD',
            default => 'PLN',
        };
        $period = match (true) {
            str_contains($t, 'godz') || str_contains($t, '/h') || str_contains($t, 'hour') => 'hour',
            str_contains($t, 'dzie') || str_contains($t, 'day') => 'day',
            str_contains($t, 'rok') || str_contains($t, 'year') => 'year',
            default => 'month',
        };

        return [$nums[0], $nums[1] ?? $nums[0], $currency, $period];
    }
}
