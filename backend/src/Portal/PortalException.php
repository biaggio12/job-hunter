<?php

namespace App\Portal;

class PortalException extends \RuntimeException
{
    public static function blocked(string $portal, int $status): self
    {
        return new self(\sprintf(
            '%s blokuje automatyczne pobieranie (%s). '
            .'Adapter celowo nie obchodzi takich zabezpieczeń — wyłącz portal lub spróbuj później.',
            $portal,
            $status >= 400 ? 'HTTP '.$status.', ochrona anty-botowa' : 'przekierowanie na stronę „zaktualizuj przeglądarkę”',
        ));
    }

    public static function structureChanged(string $portal, string $detail): self
    {
        return new self(\sprintf('%s: nie udało się odczytać danych — portal mógł zmienić strukturę strony (%s).', $portal, $detail));
    }
}
