<?php

namespace App\Enums;

enum GamePhase: string
{
    case Opening    = 'opening';
    case Middlegame = 'middlegame';
    case Endgame    = 'endgame';
}
