<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum InterviewRecommendation: string
{
    case StrongHire = 'strong_hire';
    case Hire = 'hire';
    case Maybe = 'maybe';
    case NoHire = 'no_hire';
}
