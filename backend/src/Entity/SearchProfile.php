<?php

namespace App\Entity;

use App\Repository\SearchProfileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Profil wyszukiwania (US-A3). Każdy adapter tłumaczy go na własne parametry (URL / filtry API).
 */
#[ORM\Entity(repositoryClass: SearchProfileRepository::class)]
class SearchProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name;

    /** @var list<string> słowa kluczowe — każde to osobne zapytanie do portalu */
    #[ORM\Column(type: Types::JSON)]
    private array $keywords = [];

    /** @var list<string> wartości WorkMode */
    #[ORM\Column(type: Types::JSON)]
    private array $workModes = [];

    /** @var list<string> junior|mid|senior */
    #[ORM\Column(type: Types::JSON)]
    private array $seniorities = [];

    /** @var list<string> miasta (pusta lista = cała Polska) */
    #[ORM\Column(type: Types::JSON)]
    private array $locations = [];

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    /** @return list<string> */
    public function getKeywords(): array { return $this->keywords; }
    /** @param list<string> $v */
    public function setKeywords(array $v): void { $this->keywords = self::clean($v); }
    /** @return list<string> */
    public function getWorkModes(): array { return $this->workModes; }
    /** @param list<string> $v */
    public function setWorkModes(array $v): void { $this->workModes = self::clean($v); }
    /** @return list<string> */
    public function getSeniorities(): array { return $this->seniorities; }
    /** @param list<string> $v */
    public function setSeniorities(array $v): void { $this->seniorities = self::clean($v); }
    /** @return list<string> */
    public function getLocations(): array { return $this->locations; }
    /** @param list<string> $v */
    public function setLocations(array $v): void { $this->locations = self::clean($v); }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): void { $this->active = $active; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function wantsWorkMode(string $mode): bool
    {
        return [] === $this->workModes || \in_array($mode, $this->workModes, true);
    }

    public function wantsSeniority(?string $seniority): bool
    {
        return [] === $this->seniorities || null === $seniority || \in_array($seniority, $this->seniorities, true);
    }

    /**
     * @param array<mixed> $values
     * @return list<string>
     */
    private static function clean(array $values): array
    {
        $out = [];
        foreach ($values as $v) {
            $v = trim((string) $v);
            if ('' !== $v && !\in_array($v, $out, true)) {
                $out[] = $v;
            }
        }

        return $out;
    }
}
