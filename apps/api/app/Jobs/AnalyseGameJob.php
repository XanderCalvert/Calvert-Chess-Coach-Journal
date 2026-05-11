<?php

namespace App\Jobs;

use App\Enums\AnalysisStatus;
use App\Enums\ExplanationStatus;
use App\Enums\GamePhase;
use App\Enums\MoveClassification;
use App\Models\EngineAnalysis;
use App\Models\Game;
use App\Models\KeyMoment;
use App\Models\MistakeTag;
use App\Services\BoardAnalysisService;
use App\Services\CoachingTemplateService;
use App\Services\FenParserService;
use App\Services\MoveThemeExtractorService;
use App\Services\StockfishService;
use App\Services\ThreatDetectorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
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

        if (! $this->force && $game->analysis_status === AnalysisStatus::Analysed) {
            logger()->info("AnalyseGameJob: game {$this->gameId} already analysed, skipping");
            return;
        }

        $game->update(['analysis_status' => AnalysisStatus::Analysing]);

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

        $this->selectKeyMoments($game);

        $accuracy = $this->computeAccuracy($cpLosses);

        $game->update([
            'analysis_status' => AnalysisStatus::Analysed,
            'accuracy_pct'    => $accuracy,
            'blunder_count'   => $counts['blunder'],
            'mistake_count'   => $counts['mistake'],
            'inaccuracy_count' => $counts['inaccuracy'],
        ]);

        logger()->info("AnalyseGameJob: analysed game {$this->gameId} — accuracy={$accuracy}%");
    }

    public function failed(Throwable $e): void
    {
        logger()->error("AnalyseGameJob: failed for game {$this->gameId} — {$e->getMessage()}");

        Game::where('id', $this->gameId)->update(['analysis_status' => AnalysisStatus::Failed]);
    }

    private function selectKeyMoments(Game $game): void
    {
        // Reload moves so we have up-to-date classification and cp_loss.
        $moves = $game->moves()->orderBy('move_number')->get();

        $qualifying = $moves->filter(fn ($m) => in_array($m->classification, [
            MoveClassification::Inaccuracy,
            MoveClassification::Mistake,
            MoveClassification::Blunder,
        ], strict: true))->sortByDesc('cp_loss')->values();

        // Cluster adjacent plies: if consecutive candidates share adjacent move_numbers,
        // keep only the higher-cp_loss one (the first after sorting desc).
        $clustered = collect();
        foreach ($qualifying as $move) {
            $last = $clustered->last();
            if ($last && abs($move->move_number - $last->move_number) === 1) {
                // Adjacent — the current move already lost to the higher-cp_loss one above.
                continue;
            }
            $clustered->push($move);
        }

        $top = $clustered->take(3);

        // Preload mistake tag ids once.
        $tagIds = MistakeTag::whereIn('slug', ['missed-tactic', 'hanging-piece', 'overlooked-threat', 'positional-mistake'])
            ->pluck('id', 'slug');

        DB::transaction(function () use ($game, $top, $tagIds) {
            $game->keyMoments()->delete();

            foreach ($top->values() as $rank0 => $move) {
                $flags = $move->tactical_flags ?? [];
                $threatAwareness = $move->threat_awareness ?? [];

                if (in_array('forced_mate_present', $flags)) {
                    $tagId = $tagIds->get('missed-tactic');
                } elseif (in_array('hanging_piece', $flags)) {
                    $tagId = $tagIds->get('hanging-piece');
                } elseif (($threatAwareness['response'] ?? null) === 'not_addressed') {
                    $tagId = $tagIds->get('overlooked-threat');
                } else {
                    $tagId = $tagIds->get('positional-mistake');
                }

                $moveNum = $move->move_number;
                $phase = match (true) {
                    $moveNum <= 30 => GamePhase::Opening,     // ply 30 ≈ move 15
                    $moveNum <= 70 => GamePhase::Middlegame,  // ply 70 ≈ move 35
                    default        => GamePhase::Endgame,
                };

                KeyMoment::create([
                    'game_id'            => $game->id,
                    'move_id'            => $move->id,
                    'mistake_tag_id'     => $tagId,
                    'rank'               => $rank0 + 1,
                    'cp_loss'            => $move->cp_loss,
                    'game_phase'         => $phase,
                    'explanation_status' => ExplanationStatus::NotRequested,
                ]);
            }
        });
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
