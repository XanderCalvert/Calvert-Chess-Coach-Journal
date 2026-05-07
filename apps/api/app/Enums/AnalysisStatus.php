<?php

namespace App\Enums;

enum AnalysisStatus: string
{
    case Pending  = 'pending';
    case Running  = 'running';
    case Complete = 'complete';
    case Failed   = 'failed';
}
