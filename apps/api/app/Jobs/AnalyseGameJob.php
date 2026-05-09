<?php

namespace App\Jobs;

use App\Enums\AnalysisStatus;
use App\Enums\MoveClassification;
use App\Models\EngineAnalysis;
use App\Models\Game;
use App\Services\BoardAnalysisService;
use App\Services\CoachingTemplateService;
use App\Services\FenParserService;
use App\Services\MoveThemeExtractorService;
use App\Services\StockfishService;
use App\Services\ThreatDetectorService;
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

        $stockfish    = new StockfishService();
        $fenParser    = new FenParserService();
        $boardAnalysis = new BoardAnalysisService($fenParser);
        $themeExtractor = new MoveThemeExtractorService($fenParser, $boardAnalysis);
        $threatDetector = new ThreatDetectorService($fenParser, $boardAnalysis);
        $templateService = new CoachingTemplateService();

        $cpLosses   = [];
        $counts     = ['blunder' => 0, 'mistake' => 0, 'inaccuracy' => 0];
        $userColour = $game->user_colour?->value;
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

            if ($move->colour->value === $userColour && isset($counts[$classification->value])) {
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

            // Deterministic coaching layer — no additional Stockfish calls
            $themes    = $themeExtractor->extract($move->fen_before, $move->fen_after, $move->uci, $move->move_number, $move->colour->value);
            $threatData = $threatDetector->analyse($move->fen_before, $move->fen_after, $move->uci, $before, $after);
            $riskNote  = $templateService->buildRiskNote($classification, $themes, $threatData['tactical_flags'], $threatData['threat_awareness']);

            $move->update([
                'cp_score'         => $scoreBefore,
                'cp_loss'          => $cpLoss,
                'classification'   => $classification,
                'themes'           => $themes,
                'tactical_flags'   => $threatData['tactical_flags'],
                'threat_awareness' => $threatData['threat_awareness'],
                'risk_note'        => $riskNote,
                'coaching_version' => 1,
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
            $cpLoss <= 30  => MoveClassification::Excellent,
            $cpLoss <= 80  => MoveClassification::Good,
            $cpLoss <= 140 => MoveClassification::Inaccuracy,
            $cpLoss <= 300 => MoveClassification::Mistake,
            default        => MoveClassification::Blunder,
        };
    }

    /** @param list<int> $cpLosses */
    private function computeAccuracy(array $cpLosses): float
    {
        if (empty($cpLosses)) {
            return 0.0;
        }

        // Chess.com-style ACPL formula expects pawn units, not centipawns.
        $avgCpLossInPawns = (array_sum($cpLosses) / count($cpLosses)) / 100.0;
        $accuracy = 103.1668 * exp(-0.04354 * $avgCpLossInPawns) - 3.1669;

        return round(max(0.0, min(100.0, $accuracy)), 2);
    }
}
