<?php

namespace App\Enums;

enum PhaseHint: string
{
    case Any        = 'any';
    case Opening    = 'opening';
    case Middlegame = 'middlegame';
    case Endgame    = 'endgame';
}
