<?php

namespace App\Portal;

/** Ogłoszenie w ujednoliconym formacie zwracane przez każdy adapter. */
final class ScrapedOffer
{
    /**
     * @param string|null  $workMode    remote|hybrid|office
     * @param int|null     $salaryFrom  kwota miesięczna
     * @param string|null  $contractType b2b|uop|uz|other
     * @param list<string> $tags
     * @param string|null  $seniority   junior|mid|senior|lead
     */
    public function __construct(
        public readonly string $externalId,
        public readonly string $url,
        public readonly string $title,
        public readonly string $company,
        public readonly ?string $location = null,
        public readonly ?string $workMode = null,
        public readonly ?int $salaryFrom = null,
        public readonly ?int $salaryTo = null,
        public readonly ?string $salaryCurrency = null,
        public readonly ?string $contractType = null,
        public readonly array $tags = [],
        public readonly ?string $seniority = null,
        public readonly ?\DateTimeImmutable $publishedAt = null,
    ) {
    }
}
