<?php

namespace App\Dto;

use App\Entity\SearchProfile;
use Symfony\Component\Validator\Constraints as Assert;

final class ProfileInput
{
    /**
     * @param list<string> $keywords
     * @param list<string> $workModes
     * @param list<string> $seniorities
     * @param list<string> $locations
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Podaj nazwę profilu.')]
        #[Assert\Length(max: 120)]
        public readonly string $name = '',
        #[Assert\Count(max: 10, maxMessage: 'Maksymalnie 10 słów kluczowych.')]
        #[Assert\All([new Assert\Type('string'), new Assert\Length(max: 60)])]
        public readonly array $keywords = [],
        #[Assert\All([new Assert\Choice(choices: ['remote', 'hybrid', 'office'])])]
        public readonly array $workModes = [],
        #[Assert\All([new Assert\Choice(choices: ['junior', 'mid', 'senior', 'lead'])])]
        public readonly array $seniorities = [],
        #[Assert\Count(max: 10)]
        #[Assert\All([new Assert\Type('string'), new Assert\Length(max: 60)])]
        public readonly array $locations = [],
        public readonly bool $active = true,
    ) {
    }

    public function applyTo(SearchProfile $profile): void
    {
        $profile->setName(trim($this->name));
        $profile->setKeywords($this->keywords);
        $profile->setWorkModes($this->workModes);
        $profile->setSeniorities($this->seniorities);
        $profile->setLocations($this->locations);
        $profile->setActive($this->active);
    }
}
