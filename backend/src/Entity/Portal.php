<?php

namespace App\Entity;

use App\Repository\PortalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Portal z ogłoszeniami. Wiersze są synchronizowane z adapterami zarejestrowanymi w kodzie
 * (patrz PortalSynchronizer) — dodanie adaptera automatycznie dodaje portal.
 */
#[ORM\Entity(repositoryClass: PortalRepository::class)]
class Portal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $code;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $homepage;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSuccessfulScanAt = null;

    /** Liczba kolejnych nieudanych skanów (błąd lub 0 ogłoszeń) — do alertu US-F2. */
    #[ORM\Column]
    private int $consecutiveFailures = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $failureAlertSentAt = null;

    public function __construct(string $code, string $name, string $homepage)
    {
        $this->code = $code;
        $this->name = $name;
        $this->homepage = $homepage;
    }

    public function getId(): ?int { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    public function getHomepage(): string { return $this->homepage; }
    public function setHomepage(string $homepage): void { $this->homepage = $homepage; }
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $enabled): void { $this->enabled = $enabled; }
    public function getLastSuccessfulScanAt(): ?\DateTimeImmutable { return $this->lastSuccessfulScanAt; }
    public function getConsecutiveFailures(): int { return $this->consecutiveFailures; }
    public function getFailureAlertSentAt(): ?\DateTimeImmutable { return $this->failureAlertSentAt; }

    public function recordSuccess(\DateTimeImmutable $at): void
    {
        $this->lastSuccessfulScanAt = $at;
        $this->consecutiveFailures = 0;
        $this->failureAlertSentAt = null;
    }

    public function recordFailure(): void
    {
        ++$this->consecutiveFailures;
    }

    public function markFailureAlertSent(\DateTimeImmutable $at): void
    {
        $this->failureAlertSentAt = $at;
    }
}
