<?php

namespace App\Enums;

enum ExplanationStatus: string
{
    case Pending  = 'pending';
    case Complete = 'complete';
    case Failed   = 'failed';
}
