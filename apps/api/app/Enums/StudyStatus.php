<?php

namespace App\Enums;

enum StudyStatus: string
{
    case Active     = 'active';
    case InProgress = 'in_progress';
    case Done       = 'done';
    case Dismissed  = 'dismissed';
}
