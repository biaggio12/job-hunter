<?php

namespace App\Entity;

use App\Enum\ApplicationStatus;
use App\Enum\RecruitmentStage;
use App\Repository\JobOfferRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobOfferRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_offer_portal_external', columns: ['portal_id', 'external_id'])]
#[ORM\Index(name: 'idx_offer_first_seen', columns: ['first_seen_at'])]
#[ORM\Index(name: 'idx_offer_duplicate_key', columns: ['duplicate_key'])]
#[ORM\Index(name: 'idx_offer_notification_pending', columns: ['notification_pending'])]
class JobOffer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Portal $portal;

    #[ORM\Column(length: 255)]
    private string $externalId;

    #[ORM\Column(length: 1000)]
    private string $url;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 255)]
    private string $company;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    /** remote|hybrid|office */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $workMode = null;

    /** Widełki znormalizowane do kwoty miesięcznej. */
    #[ORM\Column(nullable: true)]
    private ?int $salaryFrom = null;

    #[ORM\Column(nullable: true)]
    private ?int $salaryTo = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $salaryCurrency = null;

    /** b2b|uop|uz|other */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $contractType = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $tags = [];

    /** junior|mid|senior|lead */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $seniority = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $firstSeenAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $lastSeenAt;

    /** Liczba kolejnych udanych skanów portalu, w których ogłoszenia nie było (US-B6). */
    #[ORM\Column]
    private int $missedScans = 0;

    #[ORM\Column]
    private bool $expired = false;

    #[ORM\Column(length: 20, enumType: ApplicationStatus::class)]
    private ApplicationStatus $status = ApplicationStatus::None;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $appliedAt = null;

    #[ORM\Column(length: 20, nullable: true, enumType: RecruitmentStage::class)]
    private ?RecruitmentStage $stage = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $viewedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    /** Oczekuje na wysłanie powiadomienia (US-E1, US-E4). */
    #[ORM\Column]
    private bool $notificationPending = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $notifiedAt = null;

    /** Klucz „firma|tytuł” do wykrywania duplikatów między portalami (US-B5). */
    #[ORM\Column(length: 255)]
    private string $duplicateKey = '';

    /** @var Collection<int, StageChange> */
    #[ORM\OneToMany(targetEntity: StageChange::class, mappedBy: 'offer', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['changedAt' => 'ASC', 'id' => 'ASC'])]
    private Collection $stageHistory;

    public function __construct(Portal $portal, string $externalId, \DateTimeImmutable $seenAt)
    {
        $this->portal = $portal;
        $this->externalId = $externalId;
        $this->firstSeenAt = $seenAt;
        $this->lastSeenAt = $seenAt;
        $this->stageHistory = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getPortal(): Portal { return $this->portal; }
    public function getExternalId(): string { return $this->externalId; }
    public function getUrl(): string { return $this->url; }
    public function setUrl(string $url): void { $this->url = mb_substr($url, 0, 1000); }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): void { $this->title = mb_substr($title, 0, 255); }
    public function getCompany(): string { return $this->company; }
    public function setCompany(string $company): void { $this->company = mb_substr($company, 0, 255); }
    public function getLocation(): ?string { return $this->location; }
    public function setLocation(?string $location): void { $this->location = null === $location ? null : mb_substr($location, 0, 255); }
    public function getWorkMode(): ?string { return $this->workMode; }
    public function setWorkMode(?string $workMode): void { $this->workMode = $workMode; }
    public function getSalaryFrom(): ?int { return $this->salaryFrom; }
    public function getSalaryTo(): ?int { return $this->salaryTo; }
    public function getSalaryCurrency(): ?string { return $this->salaryCurrency; }
    public function setSalary(?int $from, ?int $to, ?string $currency): void
    {
        $this->salaryFrom = $from;
        $this->salaryTo = $to;
        $this->salaryCurrency = (null === $from && null === $to) ? null : $currency;
    }
    public function getContractType(): ?string { return $this->contractType; }
    public function setContractType(?string $contractType): void { $this->contractType = $contractType; }
    /** @return list<string> */
    public function getTags(): array { return $this->tags; }
    /** @param list<string> $tags */
    public function setTags(array $tags): void { $this->tags = array_values(array_unique($tags)); }
    public function getSeniority(): ?string { return $this->seniority; }
    public function setSeniority(?string $seniority): void { $this->seniority = $seniority; }
    public function getPublishedAt(): ?\DateTimeImmutable { return $this->publishedAt; }
    public function setPublishedAt(?\DateTimeImmutable $publishedAt): void { $this->publishedAt = $publishedAt; }
    public function getFirstSeenAt(): \DateTimeImmutable { return $this->firstSeenAt; }
    public function getLastSeenAt(): \DateTimeImmutable { return $this->lastSeenAt; }
    public function getMissedScans(): int { return $this->missedScans; }
    public function isExpired(): bool { return $this->expired; }
    public function getStatus(): ApplicationStatus { return $this->status; }
    public function getAppliedAt(): ?\DateTimeImmutable { return $this->appliedAt; }
    public function getStage(): ?RecruitmentStage { return $this->stage; }
    public function getViewedAt(): ?\DateTimeImmutable { return $this->viewedAt; }
    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $note): void { $this->note = (null === $note || '' === trim($note)) ? null : $note; }
    public function isNotificationPending(): bool { return $this->notificationPending; }
    public function getNotifiedAt(): ?\DateTimeImmutable { return $this->notifiedAt; }
    public function getDuplicateKey(): string { return $this->duplicateKey; }
    public function setDuplicateKey(string $key): void { $this->duplicateKey = mb_substr($key, 0, 255); }
    /** @return Collection<int, StageChange> */
    public function getStageHistory(): Collection { return $this->stageHistory; }

    public function markSeen(\DateTimeImmutable $at): void
    {
        $this->lastSeenAt = $at;
        $this->missedScans = 0;
        $this->expired = false;
    }

    public function markMissed(int $expireAfter): void
    {
        ++$this->missedScans;
        if ($this->missedScans >= $expireAfter) {
            $this->expired = true;
        }
    }

    public function markViewed(\DateTimeImmutable $at): void
    {
        $this->viewedAt ??= $at;
    }

    public function apply(\DateTimeImmutable $at): void
    {
        $this->status = ApplicationStatus::Applied;
        $this->appliedAt = $at;
        $this->markViewed($at);
        if (null === $this->stage) {
            $this->changeStage(RecruitmentStage::Applied, $at);
        }
    }

    public function unapply(\DateTimeImmutable $at): void
    {
        if (ApplicationStatus::Applied !== $this->status) {
            return;
        }
        $this->status = ApplicationStatus::None;
        $this->appliedAt = null;
        $this->changeStage(null, $at);
    }

    public function setStatus(ApplicationStatus $status, \DateTimeImmutable $at): void
    {
        if ($status === $this->status) {
            return;
        }
        if (ApplicationStatus::Applied === $status) {
            $this->apply($at);

            return;
        }
        if (ApplicationStatus::Applied === $this->status) {
            $this->unapply($at);
        }
        $this->status = $status;
        if (ApplicationStatus::None !== $status) {
            $this->notificationPending = false;
        }
    }

    public function changeStage(?RecruitmentStage $stage, \DateTimeImmutable $at): void
    {
        if ($stage === $this->stage) {
            return;
        }
        $this->stageHistory->add(new StageChange($this, $this->stage, $stage, $at));
        $this->stage = $stage;
        if (null !== $stage && ApplicationStatus::Applied !== $this->status) {
            $this->status = ApplicationStatus::Applied;
            $this->appliedAt ??= $at;
        }
    }

    public function queueNotification(): void
    {
        if (null === $this->notifiedAt) {
            $this->notificationPending = true;
        }
    }

    public function markNotified(\DateTimeImmutable $at): void
    {
        $this->notificationPending = false;
        $this->notifiedAt = $at;
    }
}
