<?php

namespace App\Message;

/** Skan jednego portalu — każdy portal to osobna wiadomość, więc awarie są izolowane. */
final class ScanPortalMessage
{
    public function __construct(public readonly int $scanRunId)
    {
    }
}
