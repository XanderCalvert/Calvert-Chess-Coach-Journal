<?php

namespace App\Enums;

enum CoachAgreement: string
{
    case Agreed    = 'agreed';
    case Disagreed = 'disagreed';
    case NotSet    = 'not_set';
}
