<?php

namespace App\Enum;

enum ApplicationStatus: string
{
    case None = 'none';          // nowe / jeszcze bez decyzji
    case Applied = 'applied';    // zaaplikowano
    case Rejected = 'rejected';  // odrzucone przeze mnie ("nie interesuje mnie")
    case Hidden = 'hidden';      // ukryte (np. czarna lista)
}
