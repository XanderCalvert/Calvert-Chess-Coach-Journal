<?php

namespace App\Enums;

enum AnalysisStatus: string
{
    case Pending   = 'pending';
    case Queued    = 'queued';
    case Analysing = 'analysing';
    case Analysed  = 'analysed';
    case Failed    = 'failed';
}
