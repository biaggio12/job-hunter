<?php

namespace App\Entity;

use App\Repository\BlacklistedCompanyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Czarna lista firm (US-D5). Porównanie po znormalizowanej nazwie. */
#[ORM\Entity(repositoryClass: BlacklistedCompanyRepository::class)]
class BlacklistedCompany
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255, unique: true)]
    private string $normalizedName;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $name, string $normalizedName)
    {
        $this->name = $name;
        $this->normalizedName = $normalizedName;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getNormalizedName(): string { return $this->normalizedName; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
