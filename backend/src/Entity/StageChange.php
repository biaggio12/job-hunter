<?php

namespace App\Entity;

use App\Enum\RecruitmentStage;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Historia zmian etapu rekrutacji (US-D3). */
#[ORM\Entity]
class StageChange
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'stageHistory')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private JobOffer $offer;

    #[ORM\Column(length: 20, nullable: true, enumType: RecruitmentStage::class)]
    private ?RecruitmentStage $fromStage;

    #[ORM\Column(length: 20, nullable: true, enumType: RecruitmentStage::class)]
    private ?RecruitmentStage $toStage;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $changedAt;

    public function __construct(JobOffer $offer, ?RecruitmentStage $from, ?RecruitmentStage $to, \DateTimeImmutable $at)
    {
        $this->offer = $offer;
        $this->fromStage = $from;
        $this->toStage = $to;
        $this->changedAt = $at;
    }

    public function getId(): ?int { return $this->id; }
    public function getFromStage(): ?RecruitmentStage { return $this->fromStage; }
    public function getToStage(): ?RecruitmentStage { return $this->toStage; }
    public function getChangedAt(): \DateTimeImmutable { return $this->changedAt; }
}
