<?php

namespace App\Service;

use App\Entity\JobOffer;
use App\Entity\SearchProfile;
use App\Entity\Settings;
use App\Portal\ScrapedOffer;
use App\Util\Text;

/** Dopasowanie ogłoszeń do profilu wyszukiwania (US-A3) i reguł powiadomień (US-E3). */
final class OfferMatcher
{
    /**
     * Doprecyzowanie po stronie aplikacji — nie każdy portal obsługuje każdy filtr w API.
     * Brakujące dane (np. brak poziomu) nie wykluczają ogłoszenia.
     */
    public function matchesProfile(ScrapedOffer $offer, SearchProfile $profile): bool
    {
        if (null !== $offer->workMode && !$profile->wantsWorkMode($offer->workMode)) {
            return false;
        }
        if (!$profile->wantsSeniority($offer->seniority)) {
            return false;
        }
        // Wyszukiwarki portali są pełnotekstowe i „rozmyte” (np. justjoin.it zwraca SAP na hasło „php”),
        // więc wymagamy, by któreś słowo kluczowe wystąpiło w tytule lub technologiach.
        if ([] !== $profile->getKeywords()) {
            $haystack = ' '.Text::normalize($offer->title.' '.implode(' ', $offer->tags)).' ';
            $hit = false;
            foreach ($profile->getKeywords() as $kw) {
                $needle = Text::normalize($kw);
                if ('' !== $needle && str_contains($haystack, $needle)) {
                    $hit = true;
                    break;
                }
            }
            if (!$hit) {
                return false;
            }
        }
        $locations = $profile->getLocations();
        if ([] !== $locations && 'remote' !== $offer->workMode && null !== $offer->location) {
            $loc = Text::normalize($offer->location);
            $any = false;
            foreach ($locations as $l) {
                if (str_contains($loc, Text::normalize($l))) {
                    $any = true;
                    break;
                }
            }
            if (!$any) {
                return false;
            }
        }

        return true;
    }

    public function passesNotificationRules(JobOffer $offer, Settings $settings): bool
    {
        if ($settings->notifyRemoteOnly && 'remote' !== $offer->getWorkMode()) {
            return false;
        }
        if (null !== $settings->notifyMinSalary && $settings->notifyMinSalary > 0) {
            $top = $offer->getSalaryTo() ?? $offer->getSalaryFrom();
            if (null === $top) {
                if (!$settings->notifyIncludeNoSalary) {
                    return false;
                }
            } elseif ('PLN' === ($offer->getSalaryCurrency() ?? 'PLN') && $top < $settings->notifyMinSalary) {
                return false;
            }
        }
        if ([] !== $settings->notifyKeywords) {
            $haystack = Text::normalize($offer->getTitle().' '.implode(' ', $offer->getTags()));
            foreach ($settings->notifyKeywords as $kw) {
                if ('' !== trim($kw) && str_contains($haystack, Text::normalize($kw))) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }
}
