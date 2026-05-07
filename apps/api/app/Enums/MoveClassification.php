<?php

namespace App\Enums;

enum MoveClassification: string
{
    case Best       = 'best';
    case Excellent  = 'excellent';
    case Good       = 'good';
    case Inaccuracy = 'inaccuracy';
    case Mistake    = 'mistake';
    case Blunder    = 'blunder';
}
