<?php

namespace App\Enums;

enum ExplanationStatus: string
{
    case NotRequested = 'not_requested';
    case Pending      = 'pending';
    case Complete     = 'complete';
    case Failed       = 'failed';
}
