<?php

namespace App\Portal;

use App\Entity\SearchProfile;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Wspólny interfejs adapterów portali. Dodanie nowego portalu = nowa klasa implementująca
 * ten interfejs (rejestracja i wpis w bazie dzieją się automatycznie).
 */
#[AutoconfigureTag(self::TAG)]
interface PortalAdapterInterface
{
    public const TAG = 'app.portal_adapter';

    /** Stały, unikalny identyfikator (np. „justjoin”). */
    public function getCode(): string;

    public function getName(): string;

    public function getHomepage(): string;

    /**
     * Pobiera ogłoszenia pasujące do profilu. Adapter tłumaczy profil na parametry portalu
     * i sam pilnuje limitu stron (przez PoliteHttpClient).
     *
     * @return iterable<ScrapedOffer>
     *
     * @throws PortalException
     */
    public function fetch(SearchProfile $profile): iterable;
}
