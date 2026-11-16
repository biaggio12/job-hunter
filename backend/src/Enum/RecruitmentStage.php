<?php

namespace App\Enum;

enum RecruitmentStage: string
{
    case Applied = 'applied';
    case Interview = 'interview';
    case Task = 'task';
    case Offer = 'offer';
    case Rejected = 'rejected';
}
