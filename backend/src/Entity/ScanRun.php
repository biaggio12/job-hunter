<?php

namespace App\Entity;

use App\Enum\ScanStatus;
use App\Enum\ScanTrigger;
use App\Repository\ScanRunRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Pojedynczy skan portalu (US-F1). */
#[ORM\Entity(repositoryClass: ScanRunRepository::class)]
#[ORM\Index(name: 'idx_scan_created', columns: ['created_at'])]
class ScanRun
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Portal $portal;

    #[ORM\Column(length: 20, enumType: ScanTrigger::class)]
    private ScanTrigger $trigger;

    #[ORM\Column(length: 20, enumType: ScanStatus::class)]
    private ScanStatus $status = ScanStatus::Queued;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column]
    private int $foundCount = 0;

    #[ORM\Column]
    private int $newCount = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error = null;

    public function __construct(Portal $portal, ScanTrigger $trigger)
    {
        $this->portal = $portal;
        $this->trigger = $trigger;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getPortal(): Portal { return $this->portal; }
    public function getTrigger(): ScanTrigger { return $this->trigger; }
    public function getStatus(): ScanStatus { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getStartedAt(): ?\DateTimeImmutable { return $this->startedAt; }
    public function getFinishedAt(): ?\DateTimeImmutable { return $this->finishedAt; }
    public function getFoundCount(): int { return $this->foundCount; }
    public function getNewCount(): int { return $this->newCount; }
    public function getError(): ?string { return $this->error; }

    public function start(): void
    {
        $this->status = ScanStatus::Running;
        $this->startedAt = new \DateTimeImmutable();
    }

    public function succeed(int $found, int $new, ?string $warning = null): void
    {
        $this->status = ScanStatus::Success;
        $this->finishedAt = new \DateTimeImmutable();
        $this->foundCount = $found;
        $this->newCount = $new;
        $this->error = $warning;
    }

    public function fail(string $error, int $found = 0, int $new = 0): void
    {
        $this->status = ScanStatus::Failed;
        $this->finishedAt = new \DateTimeImmutable();
        $this->foundCount = $found;
        $this->newCount = $new;
        $this->error = mb_substr($error, 0, 4000);
    }
}
