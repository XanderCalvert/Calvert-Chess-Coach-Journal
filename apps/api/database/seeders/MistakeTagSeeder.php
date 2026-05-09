<?php

namespace Database\Seeders;

use App\Models\MistakeTag;
use Illuminate\Database\Seeder;

class MistakeTagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            ['slug' => 'hanging-piece',     'label' => 'Hanging Piece',              'phase_hint' => 'any',        'description' => 'A piece is left on a square attacked more times than defended'],
            ['slug' => 'missed-tactic',     'label' => 'Missed Tactic',              'phase_hint' => 'any',        'description' => 'A tactical sequence (fork, pin, skewer, discovered attack) was available but not taken'],
            ['slug' => 'missed-capture',    'label' => 'Missed Capture',             'phase_hint' => 'any',        'description' => 'An opponent piece was en prise and was not captured'],
            ['slug' => 'king-safety',       'label' => 'King Safety',                'phase_hint' => 'middlegame', 'description' => "The castled king's pawn shelter is broken, or the king was left on an open file/rank"],
            ['slug' => 'poor-development',  'label' => 'Poor Development',           'phase_hint' => 'opening',    'description' => 'A piece moved twice when all pieces have not yet left their starting squares'],
            ['slug' => 'bad-trade',         'label' => 'Bad Trade',                  'phase_hint' => 'any',        'description' => 'A piece of higher value was exchanged for lower value with no positional compensation'],
            ['slug' => 'pawn-weakness',     'label' => 'Pawn Weakness',              'phase_hint' => 'any',        'description' => 'A pawn push created an isolated, doubled, or backward pawn without adequate compensation'],
            ['slug' => 'opening-principle', 'label' => 'Opening Principle Issue',    'phase_hint' => 'opening',    'description' => 'Centre control ceded, a bishop/knight undeveloped, or king uncastled past move 15'],
            ['slug' => 'endgame-technique', 'label' => 'Endgame Technique',          'phase_hint' => 'endgame',    'description' => 'King not activated, passed pawn not advanced, or basic theoretical position mishandled'],
            ['slug' => 'overlooked-threat', 'label' => 'Overlooked Opponent Threat', 'phase_hint' => 'any',        'description' => "Opponent's previous move had a clear threat (check, capture, promotion) that was not addressed"],
            ['slug' => 'time-pressure',     'label' => 'Time Pressure Blunder',      'phase_hint' => 'any',        'description' => 'Flagged manually by user or inferred from clock data in PGN'],
            ['slug' => 'positional-mistake','label' => 'Positional Mistake',         'phase_hint' => 'any',        'description' => 'Catch-all for positional errors (bad piece placement, wrong plan) not fitting another category'],
        ];

        foreach ($tags as $tag) {
            MistakeTag::updateOrCreate(['slug' => $tag['slug']], $tag);
        }
    }
}
