<?php

namespace App\Enums;

enum GameResult: string
{
    case White   = 'white';
    case Black   = 'black';
    case Draw    = 'draw';
    case Unknown = 'unknown';
}
