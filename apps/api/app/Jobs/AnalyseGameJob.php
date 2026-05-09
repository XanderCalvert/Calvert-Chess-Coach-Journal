<?php

namespace App\Jobs;

use App\Enums\AnalysisStatus;
use App\Enums\MoveClassification;
use App\Models\EngineAnalysis;
use App\Models\Game;
use App\Services\StockfishService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class AnalyseGameJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(
        public readonly string $gameId,
        public readonly bool $force = false,
    ) {}

    public function handle(): void
    {
        $game = Game::with(['moves' => fn ($q) => $q->orderBy('move_number')->orderBy('colour')])->findOrFail($this->gameId);

        if (! $this->force && $game->analysis_status === AnalysisStatus::Complete) {
            logger()->info("AnalyseGameJob: game {$this->gameId} already complete, skipping");
            return;
        }

        $game->update(['analysis_status' => AnalysisStatus::Running]);

        logger()->info("AnalyseGameJob: starting analysis for game {$this->gameId} ({$game->moves->count()} moves)");

        $stockfish  = new StockfishService();
        $cpLosses   = [];
        $counts     = ['blunder' => 0, 'mistake' => 0, 'inaccuracy' => 0];
        $depth      = config('services.stockfish.depth');

        foreach ($game->moves as $move) {
            $before = $stockfish->analyse($move->fen_before);
            $after  = $stockfish->analyse($move->fen_after);

            // fen_after is from the opponent's perspective — negate to get same player's view
            $scoreBefore = $before['cp'];
            $scoreAfter  = -$after['cp'];
            $cpLoss      = max(0, $scoreBefore - $scoreAfter);
            $cpLosses[]  = $cpLoss;

            $classification = $this->classify($cpLoss, $move->uci, $before['best_move']);

            if (isset($counts[$classification->value])) {
                $counts[$classification->value]++;
            }

            EngineAnalysis::updateOrCreate(
                ['move_id' => $move->id, 'engine_name' => 'stockfish'],
                [
                    'best_move_uci'  => $before['best_move'],
                    'best_move_san'  => null,
                    'best_line'      => $before['best_line'],
                    'depth'          => $before['depth_reached'],
                    'depth_requested' => $depth,
                    'depth_reached'  => $before['depth_reached'],
                    'cp_evaluation'  => $scoreBefore,
                    'analysed_at'    => now(),
                ]
            );

            $move->update([
                'cp_score'       => $scoreBefore,
                'cp_loss'        => $cpLoss,
                'classification' => $classification,
            ]);

            logger()->debug("AnalyseGameJob: move {$move->move_number} {$move->san} — cp_loss={$cpLoss} class={$classification->value}");
        }

        $accuracy = $this->computeAccuracy($cpLosses);

        $game->update([
            'analysis_status' => AnalysisStatus::Complete,
            'accuracy_pct'    => $accuracy,
            'blunder_count'   => $counts['blunder'],
            'mistake_count'   => $counts['mistake'],
            'inaccuracy_count' => $counts['inaccuracy'],
        ]);

        logger()->info("AnalyseGameJob: complete for game {$this->gameId} — accuracy={$accuracy}%");
    }

    public function failed(Throwable $e): void
    {
        logger()->error("AnalyseGameJob: failed for game {$this->gameId} — {$e->getMessage()}");

        Game::where('id', $this->gameId)->update(['analysis_status' => AnalysisStatus::Failed]);
    }

    private function classify(int $cpLoss, string $playedUci, string $bestMove): MoveClassification
    {
        if ($cpLoss <= 10 && $playedUci === $bestMove) {
            return MoveClassification::Best;
        }

        return match (true) {
            $cpLoss <= 20  => MoveClassification::Good,
            $cpLoss <= 50  => MoveClassification::Inaccuracy,
            $cpLoss <= 150 => MoveClassification::Mistake,
            default        => MoveClassification::Blunder,
        };
    }

    /** @param list<int> $cpLosses */
    private function computeAccuracy(array $cpLosses): float
    {
        if (empty($cpLosses)) {
            return 0.0;
        }

        $avg = array_sum($cpLosses) / count($cpLosses);

        return round(103.1668 * exp(-0.04354 * $avg) - 3.1669, 2);
    }
}
