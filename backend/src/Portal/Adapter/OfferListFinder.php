<?php

namespace App\Portal\Adapter;

/**
 * Przeszukuje zagnieżdżone dane (np. __NEXT_DATA__) w poszukiwaniu listy ofert.
 * Odporne na przenoszenie danych między pageProps / dehydratedState.
 */
final class OfferListFinder
{
    /**
     * Pierwsza (najdłuższa) lista obiektów zawierających wszystkie podane klucze.
     *
     * @param list<string> $requiredKeys
     * @return list<array<string, mixed>>|null
     */
    public static function find(array $data, array $requiredKeys): ?array
    {
        $best = null;
        self::walk($data, static function (array $node) use ($requiredKeys, &$best): void {
            if (!array_is_list($node) || [] === $node || !\is_array($node[0])) {
                return;
            }
            foreach ($requiredKeys as $k) {
                if (!\array_key_exists($k, $node[0])) {
                    return;
                }
            }
            if (null === $best || \count($node) > \count($best)) {
                $best = $node;
            }
        });

        return $best;
    }

    /** @return list<mixed>|null wartość pierwszego klucza o danej nazwie, będąca listą */
    public static function findByKey(array $data, string $key): ?array
    {
        $found = null;
        self::walk($data, static function (array $node) use ($key, &$found): void {
            if (null === $found && \array_key_exists($key, $node) && \is_array($node[$key])) {
                $found = array_values($node[$key]);
            }
        });

        return $found;
    }

    private static function walk(array $node, callable $visit, int $depth = 0): void
    {
        if ($depth > 25) {
            return;
        }
        $visit($node);
        foreach ($node as $child) {
            if (\is_array($child)) {
                self::walk($child, $visit, $depth + 1);
            }
        }
    }
}
