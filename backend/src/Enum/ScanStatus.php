<?php

namespace App\Enum;

enum ScanStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';

    public function isFinished(): bool
    {
        return self::Success === $this || self::Failed === $this;
    }
}
