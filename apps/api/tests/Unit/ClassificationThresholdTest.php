<?php

namespace Tests\Unit;

use App\Enums\MoveClassification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClassificationThresholdTest extends TestCase
{
    private function classify(int $cpLoss, string $playedUci, string $bestMove): MoveClassification
    {
        if ($cpLoss <= 10 && $playedUci === $bestMove) {
            return MoveClassification::Best;
        }

        return match (true) {
            $cpLoss <= 30  => MoveClassification::Excellent,
            $cpLoss <= 80  => MoveClassification::Good,
            $cpLoss <= 140 => MoveClassification::Inaccuracy,
            $cpLoss <= 300 => MoveClassification::Mistake,
            default        => MoveClassification::Blunder,
        };
    }

    #[DataProvider('classificationProvider')]
    public function test_classification(int $cpLoss, string $played, string $best, MoveClassification $expected): void
    {
        $this->assertSame($expected, $this->classify($cpLoss, $played, $best));
    }

    public static function classificationProvider(): array
    {
        return [
            'best: cp_loss=0 played=best'             => [0,   'e2e4', 'e2e4', MoveClassification::Best],
            'best: cp_loss=10 played=best'             => [10,  'e2e4', 'e2e4', MoveClassification::Best],
            'not best: cp_loss=10 played!=best'        => [10,  'd2d4', 'e2e4', MoveClassification::Excellent],
            'not best: cp_loss=11 played=best'         => [11,  'e2e4', 'e2e4', MoveClassification::Excellent],
            'excellent boundary: cp_loss=30'           => [30,  'd2d4', 'e2e4', MoveClassification::Excellent],
            'good starts at 31'                        => [31,  'd2d4', 'e2e4', MoveClassification::Good],
            'good boundary: cp_loss=80'                => [80,  'd2d4', 'e2e4', MoveClassification::Good],
            'inaccuracy starts at 81'                  => [81,  'd2d4', 'e2e4', MoveClassification::Inaccuracy],
            'inaccuracy boundary: cp_loss=140'         => [140, 'd2d4', 'e2e4', MoveClassification::Inaccuracy],
            'mistake starts at 141'                    => [141, 'd2d4', 'e2e4', MoveClassification::Mistake],
            'mistake boundary: cp_loss=300'            => [300, 'd2d4', 'e2e4', MoveClassification::Mistake],
            'blunder starts at 301'                    => [301, 'd2d4', 'e2e4', MoveClassification::Blunder],
            'blunder large value'                      => [900, 'd2d4', 'e2e4', MoveClassification::Blunder],
            // Promotion moves — UCI with 5 chars (e.g. e7e8q)
            'best: promotion played=best'              => [0,   'e7e8q', 'e7e8q', MoveClassification::Best],
            'excellent: promotion played!=best'        => [0,   'e7e8r', 'e7e8q', MoveClassification::Excellent],
        ];
    }
}
