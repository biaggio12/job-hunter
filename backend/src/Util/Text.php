<?php

namespace App\Util;

final class Text
{
    /** Małe litery, bez polskich znaków i interpunkcji, pojedyncze spacje. */
    public static function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, [
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
            'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'é' => 'e', 'á' => 'a', 'í' => 'i', 'ú' => 'u',
        ]);
        $s = preg_replace('/[^a-z0-9+#]+/', ' ', $s) ?? $s;

        return trim(preg_replace('/\s+/', ' ', $s) ?? $s);
    }

    /** Nazwa firmy bez form prawnych („sp. z o.o.”, „S.A.” itp.). */
    public static function normalizeCompany(string $company): string
    {
        $s = ' '.self::normalize($company).' ';
        $s = preg_replace('/\s(sp z o o|sp zoo|spolka z o o|s a|sa|sp k|sp j|gmbh|ltd|inc|llc|group|poland|polska)(?=\s)/', ' ', $s) ?? $s;

        return trim(preg_replace('/\s+/', ' ', $s) ?? $s);
    }

    /**
     * Klucz grupowania duplikatów między portalami (US-B5): firma + tytuł bez
     * oznaczeń poziomu, płci i treści w nawiasach.
     */
    public static function duplicateKey(string $company, string $title): string
    {
        $t = mb_strtolower($title);
        $t = preg_replace('/\(.*?\)|\[.*?\]/u', ' ', $t) ?? $t;
        $t = ' '.self::normalize($t).' ';
        $t = preg_replace('/\s(junior|jr|mid|regular|middle|senior|sr|lead|principal|staff|expert|k m|m k|f m|m f|kobieta|mezczyzna|remote|zdalnie|hybrid|hybrydowo)(?=\s)/', ' ', $t) ?? $t;
        $t = trim(preg_replace('/\s+/', ' ', $t) ?? $t);
        $c = self::normalizeCompany($company);

        return ('' === $c || '' === $t) ? '' : $c.'|'.$t;
    }

    public static function slug(string $s): string
    {
        return str_replace(' ', '-', self::normalize($s));
    }
}
