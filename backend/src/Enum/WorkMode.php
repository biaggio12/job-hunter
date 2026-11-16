<?php

namespace App\Enum;

enum WorkMode: string
{
    case Remote = 'remote';
    case Hybrid = 'hybrid';
    case Office = 'office';
}
