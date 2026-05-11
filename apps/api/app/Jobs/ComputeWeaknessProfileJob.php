<?php

namespace App\Jobs;

use App\Enums\AnalysisStatus;
use App\Models\ConnectedAccount;
use App\Models\MistakeTag;
use App\Models\MoveTacticalEvent;
use App\Models\MoveThreatEvent;
use App\Models\WeaknessProfile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class ComputeWeaknessProfileJob implements ShouldQueue
{
    use Queueable;

    private const PROFILE_VERSION = '1.0';

    private const MOTIF_SEVERITY = [
        'forced_mate_present'    => 'critical',
        'hanging_piece'          => 'major',
        'engine_prefers_capture' => 'major',
        'possible_fork'          => 'minor',
        'possible_pin'           => 'minor',
        'possible_skewer'        => 'minor',
    ];

    private const SEVERITY_WEIGHT = [
        'critical' => 3,
        'major'    => 2,
        'minor'    => 1,
    ];

    // Motif slug → MistakeTag slug mapping for top_mistake_tag derivation
    private const MOTIF_TO_TAG = [
        'forced_mate_present'    => 'missed-tactic',
        'hanging_piece'          => 'hanging-piece',
        'engine_prefers_capture' => 'missed-capture',
        'possible_fork'          => 'missed-tactic',
        'possible_pin'           => 'missed-tactic',
        'possible_skewer'        => 'missed-tactic',
    ];

    public function __construct(public readonly string $connectedAccountId) {}

    public function handle(): void
    {
        $windowSize      = config('chess.weakness_profile_window', 20);
        $openingMinGames = config('chess.weakness_opening_min_games', 3);
        $motifMinGames   = config('chess.weakness_motif_min_games', 2);

        // Load the window of analysed games, newest first.
        $games = ConnectedAccount::findOrFail($this->connectedAccountId)
            ->games()
            ->where('analysis_status', AnalysisStatus::Analysed)
            ->orderByDesc('played_at')
            ->limit($windowSize)
            ->get(['id', 'eco_code', 'opening_name', 'accuracy_pct',
                   'blunder_count', 'mistake_count', 'inaccuracy_count', 'played_at']);

        if ($games->isEmpty()) {
            return;
        }

        $gameIds  = $games->pluck('id')->all();
        $moveIds  = $this->loadMoveIds($gameIds);

        $sufficientData = count($gameIds) >= 3;

        $phaseBreakdown        = [];
        $openingBreakdown      = [];
        $motifFrequencies      = [];
        $threatResponseByPhase = [];
        $weakestPhase          = null;
        $topMotif              = null;
        $topMistakeTagId       = null;
        $threatResponseRate    = null;

        if ($sufficientData) {
            $moves          = $this->loadMoves($gameIds);
            $tacticalEvents = $this->loadTacticalEvents($moveIds);
            $threatEvents   = $this->loadThreatEvents($gameIds);

            $phaseBreakdown        = $this->computePhaseBreakdown($moves, $tacticalEvents, $threatEvents);
            $openingBreakdown      = $this->computeOpeningBreakdown($games, $moves, $openingMinGames);
            $motifFrequencies      = $this->computeMotifFrequencies($tacticalEvents, count($gameIds), $motifMinGames);
            $threatResponseByPhase = $this->computeThreatResponseByPhase($threatEvents);
            $weakestPhase          = $this->deriveWeakestPhase($phaseBreakdown);
            $topMotif              = $motifFrequencies[0]['motif'] ?? null;
            $threatResponseRate    = $this->computeOverallThreatResponseRate($threatEvents);
            $topMistakeTagId       = $this->resolveTopMistakeTagId($topMotif);
        }

        // Games ordered newest-first; from = oldest (last), to = newest (first).
        $computedToGameId   = $games->first()->id;
        $computedFromGameId = $games->last()->id;

        WeaknessProfile::create([
            'connected_account_id'  => $this->connectedAccountId,
            'computed_at'           => now(),
            'profile_version'       => self::PROFILE_VERSION,
            'window_size'           => $windowSize,
            'analysed_games_count'  => count($gameIds),
            'computed_from_game_id' => $computedFromGameId,
            'computed_to_game_id'   => $computedToGameId,
            'weakest_phase'         => $weakestPhase,
            'top_motif'             => $topMotif,
            'top_mistake_tag_id'    => $topMistakeTagId,
            'threat_response_rate'  => $threatResponseRate,
            'phase_breakdown'       => $phaseBreakdown,
            'opening_breakdown'     => $openingBreakdown,
            'motif_frequencies'     => $motifFrequencies,
            'threat_response_by_phase' => $threatResponseByPhase,
            'summary_json'          => $this->buildSummaryJson(
                $sufficientData,
                count($gameIds),
                $windowSize,
                $weakestPhase,
                $topMotif,
                $topMistakeTagId,
                $threatResponseRate,
                $phaseBreakdown,
                $openingBreakdown,
                $motifFrequencies,
                $threatResponseByPhase,
            ),
        ]);
    }

    // -------------------------------------------------------------------------
    // Data loading (bulk, no N+1)
    // -------------------------------------------------------------------------

    private function loadMoveIds(array $gameIds): array
    {
        return \App\Models\Move::whereIn('game_id', $gameIds)
            ->whereNotNull('game_phase')
            ->pluck('id')
            ->all();
    }

    private function loadMoves(array $gameIds): Collection
    {
        return \App\Models\Move::whereIn('game_id', $gameIds)
            ->whereNotNull('game_phase')
            ->get(['id', 'game_id', 'game_phase', 'classification', 'cp_loss']);
    }

    private function loadTacticalEvents(array $moveIds): Collection
    {
        if (empty($moveIds)) {
            return collect();
        }

        return MoveTacticalEvent::whereIn('move_tactical_events.move_id', $moveIds)
            ->join('moves', 'moves.id', '=', 'move_tactical_events.move_id')
            ->get([
                'move_tactical_events.move_id',
                'move_tactical_events.motif',
                'move_tactical_events.severity',
                'moves.game_id',
                'moves.game_phase',
            ]);
    }

    private function loadThreatEvents(array $gameIds): Collection
    {
        return MoveThreatEvent::whereIn('move_threat_events.game_id', $gameIds)
            ->join('moves', 'moves.id', '=', 'move_threat_events.response_move_id')
            ->get([
                'move_threat_events.game_id',
                'move_threat_events.response_status',
                'moves.game_phase as response_phase',
            ]);
    }

    // -------------------------------------------------------------------------
    // Breakdown computations
    // -------------------------------------------------------------------------

    private function computePhaseBreakdown(Collection $moves, Collection $tacticalEvents, Collection $threatEvents): array
    {
        $phases = ['opening', 'middlegame', 'endgame'];
        $result = [];

        foreach ($phases as $phase) {
            $phaseMoves = $moves->filter(fn ($m) => $m->game_phase?->value === $phase);

            if ($phaseMoves->isEmpty()) {
                continue;
            }

            $moveCount   = $phaseMoves->count();
            $blunders    = $phaseMoves->filter(fn ($m) => $m->classification?->value === 'blunder')->count();
            $mistakes    = $phaseMoves->filter(fn ($m) => $m->classification?->value === 'mistake')->count();
            $inaccuracies = $phaseMoves->filter(fn ($m) => $m->classification?->value === 'inaccuracy')->count();
            $cpLosses    = $phaseMoves->whereNotNull('cp_loss')->pluck('cp_loss');
            $avgCpLoss   = $cpLosses->isNotEmpty() ? round($cpLosses->average(), 1) : null;
            $errorRate   = round(($blunders * 3 + $mistakes * 2 + $inaccuracies) / $moveCount, 4);

            // Top motifs in this phase (sorted by occurrence count)
            $phaseMotifs = $tacticalEvents
                ->filter(fn ($e) => $e->game_phase === $phase)
                ->groupBy('motif')
                ->map(fn ($g) => $g->count())
                ->sortDesc()
                ->keys()
                ->take(3)
                ->values()
                ->all();

            // Threat response rate for this phase
            $phaseThreats = $threatEvents->filter(fn ($e) => $e->response_phase === $phase);
            $phaseThreatTotal = $phaseThreats->count();
            $phaseThreatRate = $phaseThreatTotal > 0
                ? round($phaseThreats->filter(fn ($e) => $e->response_status === 'addressed')->count() / $phaseThreatTotal * 100, 1)
                : null;

            $result[$phase] = [
                'move_count'           => $moveCount,
                'blunders'             => $blunders,
                'mistakes'             => $mistakes,
                'inaccuracies'         => $inaccuracies,
                'avg_cp_loss'          => $avgCpLoss,
                'error_rate'           => $errorRate,
                'top_motifs'           => $phaseMotifs,
                'threat_response_rate' => $phaseThreatRate,
            ];
        }

        return $result;
    }

    private function computeOpeningBreakdown(Collection $games, Collection $moves, int $minGames): array
    {
        // Group games by eco_code; exclude openings below the minimum threshold.
        $byEco = $games->groupBy('eco_code');

        $rows = [];
        foreach ($byEco as $ecoCode => $ecoGames) {
            if ($ecoGames->count() < $minGames) {
                continue;
            }

            $ecoGameIds  = $ecoGames->pluck('id')->all();
            $ecoMoves    = $moves->whereIn('game_id', $ecoGameIds);
            $gameCount   = $ecoGames->count();
            $blunders    = $ecoMoves->filter(fn ($m) => $m->classification?->value === 'blunder')->count();
            $mistakes    = $ecoMoves->filter(fn ($m) => $m->classification?->value === 'mistake')->count();
            $inaccuracies = $ecoMoves->filter(fn ($m) => $m->classification?->value === 'inaccuracy')->count();
            $weaknessScore = round(($blunders * 3 + $mistakes * 2 + $inaccuracies) / $gameCount, 2);

            $accuracies  = $ecoGames->whereNotNull('accuracy_pct')->pluck('accuracy_pct')->map(fn ($v) => (float) $v);
            $avgAccuracy = $accuracies->isNotEmpty() ? round($accuracies->average(), 1) : null;

            $cpLosses  = $ecoMoves->whereNotNull('cp_loss')->pluck('cp_loss');
            $avgCpLoss = $cpLosses->isNotEmpty() ? round($cpLosses->average(), 1) : null;

            $rows[] = [
                'eco_code'       => $ecoCode,
                'opening_name'   => $ecoGames->first()->opening_name,
                'games'          => $gameCount,
                'avg_accuracy'   => $avgAccuracy,
                'blunders'       => $blunders,
                'mistakes'       => $mistakes,
                'inaccuracies'   => $inaccuracies,
                'avg_cp_loss'    => $avgCpLoss,
                'weakness_score' => $weaknessScore,
            ];
        }

        // Sort descending by weakness_score.
        usort($rows, fn ($a, $b) => $b['weakness_score'] <=> $a['weakness_score']);

        return $rows;
    }

    private function computeMotifFrequencies(Collection $tacticalEvents, int $gamesCount, int $minGames): array
    {
        if ($tacticalEvents->isEmpty()) {
            return [];
        }

        $byMotif = $tacticalEvents->groupBy('motif');
        $rows    = [];

        foreach ($byMotif as $motif => $events) {
            $affectedGames = $events->pluck('game_id')->unique()->count();

            if ($affectedGames < $minGames) {
                continue;
            }

            $severity      = self::MOTIF_SEVERITY[$motif] ?? 'minor';
            $frequencyRate = round($affectedGames / $gamesCount, 4);
            $score         = round($frequencyRate * (self::SEVERITY_WEIGHT[$severity] ?? 1), 4);

            // Phase distribution
            $phaseCounts = [];
            foreach ($events->groupBy('game_phase') as $phase => $phaseEvents) {
                if ($phase !== null) {
                    $phaseCounts[$phase] = $phaseEvents->count();
                }
            }

            // Dominant phase = phase with highest event count.
            if (empty($phaseCounts)) {
                $dominantPhase = null;
            } else {
                arsort($phaseCounts);
                $dominantPhase = array_key_first($phaseCounts);
            }

            $rows[] = [
                'motif'          => $motif,
                'severity'       => $severity,
                'count'          => $events->count(),
                'affected_games' => $affectedGames,
                'frequency_rate' => $frequencyRate,
                'phases'         => $phaseCounts,
                'dominant_phase' => $dominantPhase,
                'score'          => $score,
            ];
        }

        usort($rows, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $rows;
    }

    private function computeThreatResponseByPhase(Collection $threatEvents): array
    {
        $result = [];

        foreach ($threatEvents->groupBy('response_phase') as $phase => $events) {
            if ($phase === null) {
                continue;
            }
            $total     = $events->count();
            $addressed = $events->where('response_status', 'addressed')->count();
            $result[$phase] = round($addressed / $total * 100, 1);
        }

        return $result;
    }

    private function computeOverallThreatResponseRate(Collection $threatEvents): ?float
    {
        if ($threatEvents->isEmpty()) {
            return null;
        }

        $total     = $threatEvents->count();
        $addressed = $threatEvents->where('response_status', 'addressed')->count();

        return round($addressed / $total * 100, 1);
    }

    // -------------------------------------------------------------------------
    // Scalar derivation
    // -------------------------------------------------------------------------

    private function deriveWeakestPhase(array $phaseBreakdown): ?string
    {
        if (empty($phaseBreakdown)) {
            return null;
        }

        $worst      = null;
        $worstRate  = -1.0;

        foreach ($phaseBreakdown as $phase => $data) {
            if (($data['error_rate'] ?? 0) > $worstRate) {
                $worstRate = $data['error_rate'];
                $worst     = $phase;
            }
        }

        return $worst;
    }

    private function resolveTopMistakeTagId(?string $topMotif): ?string
    {
        if ($topMotif === null) {
            return null;
        }

        $slug = self::MOTIF_TO_TAG[$topMotif] ?? null;

        if ($slug === null) {
            return null;
        }

        return MistakeTag::where('slug', $slug)->value('id');
    }

    // -------------------------------------------------------------------------
    // summary_json — structured facts only, no prose
    // -------------------------------------------------------------------------

    private function buildSummaryJson(
        bool    $sufficientData,
        int     $analysedGamesCount,
        int     $windowSize,
        ?string $weakestPhase,
        ?string $topMotif,
        ?string $topMistakeTagId,
        ?float  $threatResponseRate,
        array   $phaseBreakdown,
        array   $openingBreakdown,
        array   $motifFrequencies,
        array   $threatResponseByPhase,
    ): array {
        $base = [
            'profile_version'       => self::PROFILE_VERSION,
            'computed_at'           => now()->toIso8601String(),
            'window_size'           => $windowSize,
            'analysed_games_count'  => $analysedGamesCount,
            'sufficient_data'       => $sufficientData,
        ];

        if (! $sufficientData) {
            return $base;
        }

        $phaseErrorRates = [];
        foreach ($phaseBreakdown as $phase => $data) {
            $phaseErrorRates[$phase] = $data['error_rate'] ?? 0;
        }

        $topMotifRow = $motifFrequencies[0] ?? null;

        $worstOpening = null;
        if (! empty($openingBreakdown)) {
            $w = $openingBreakdown[0];
            $worstOpening = [
                'eco_code'       => $w['eco_code'],
                'opening_name'   => $w['opening_name'],
                'weakness_score' => $w['weakness_score'],
                'games'          => $w['games'],
                'avg_accuracy'   => $w['avg_accuracy'],
            ];
        }

        // Condensed motif list for prompt budget efficiency (5 fields each)
        $condensedMotifs = array_map(fn ($m) => [
            'motif'          => $m['motif'],
            'frequency_rate' => $m['frequency_rate'],
            'severity'       => $m['severity'],
            'dominant_phase' => $m['dominant_phase'] ?? null,
            'affected_games' => $m['affected_games'],
        ], array_slice($motifFrequencies, 0, 5));

        return array_merge($base, [
            'weakest_phase'                => $weakestPhase,
            'phase_error_rates'            => $phaseErrorRates,
            'top_motif'                    => $topMotif,
            'top_motif_frequency_rate'     => $topMotifRow['frequency_rate'] ?? null,
            'top_motif_dominant_phase'     => $topMotifRow['dominant_phase'] ?? null,
            'top_mistake_tag_id'           => $topMistakeTagId,
            'threat_response_rate_overall' => $threatResponseRate,
            'threat_response_by_phase'     => $threatResponseByPhase,
            'worst_opening'                => $worstOpening,
            'motif_frequencies'            => $condensedMotifs,
            'opening_weakness_count'       => count($openingBreakdown),
            'detector_version'             => '1.0',
        ]);
    }
}
