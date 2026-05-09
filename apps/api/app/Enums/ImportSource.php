<?php

namespace App\Enums;

enum ImportSource: string
{
    case Paste    = 'paste';
    case ChessCom = 'chesscom';
    case Lichess  = 'lichess';
}
