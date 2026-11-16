<?php

namespace App\Notification;

use App\Entity\JobOffer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Wspólne formatowanie ogłoszeń do wiadomości. */
class OfferFormatter
{
    public function __construct(
        #[Autowire('%env(APP_PUBLIC_URL)%')]
        private readonly string $publicUrl,
    ) {
    }

    public function salary(JobOffer $o): string
    {
        $from = $o->getSalaryFrom();
        $to = $o->getSalaryTo();
        if (null === $from && null === $to) {
            return 'brak widełek';
        }
        $fmt = static fn (?int $v) => null === $v ? '?' : number_format($v, 0, ',', ' ');
        $range = (null !== $from && null !== $to && $from !== $to) ? $fmt($from).' – '.$fmt($to) : $fmt($to ?? $from);
        $contract = match ($o->getContractType()) {
            'b2b' => ' (B2B)',
            'uop' => ' (UoP)',
            'uz' => ' (UZ)',
            default => '',
        };

        return $range.' '.($o->getSalaryCurrency() ?? 'PLN').'/mies.'.$contract;
    }

    public function workMode(JobOffer $o): string
    {
        return match ($o->getWorkMode()) {
            'remote' => 'zdalnie',
            'hybrid' => 'hybrydowo',
            'office' => 'stacjonarnie',
            default => 'tryb nieznany',
        };
    }

    public function appUrl(JobOffer $o): ?string
    {
        $base = rtrim($this->publicUrl, '/');

        return '' === $base ? null : $base.'/offers/'.$o->getId();
    }

    /** Polska odmiana: 1 nowe ogłoszenie, 2–4 nowe ogłoszenia, 5+ nowych ogłoszeń. */
    public function countNew(int $n): string
    {
        return $n.' '.match (true) {
            1 === $n => 'nowe ogłoszenie',
            $n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) => 'nowe ogłoszenia',
            default => 'nowych ogłoszeń',
        };
    }

    public function appBaseUrl(): ?string
    {
        $base = rtrim($this->publicUrl, '/');

        return '' === $base ? null : $base;
    }
}
