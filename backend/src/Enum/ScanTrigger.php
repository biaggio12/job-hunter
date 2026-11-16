<?php

namespace App\Enum;

enum ScanTrigger: string
{
    case Scheduled = 'scheduled';
    case Manual = 'manual';
    case Console = 'console';
}
