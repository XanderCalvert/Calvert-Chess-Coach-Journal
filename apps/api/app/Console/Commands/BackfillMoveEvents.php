<?php

namespace App\Console\Commands;

use App\Enums\AnalysisStatus;
use App\Jobs\AnalyseGameJob;
use App\Models\Game;
use App\Models\MoveTacticalEvent;
use App\Models\MoveThreatEvent;
use Illuminate\Console\Command;

class BackfillMoveEvents extends Command
{
    protected $signature = 'chess:backfill-move-events
        {--game= : Limit to a specific game ID}
        {--dry-run : Preview counts without writing}';

    protected $description = 'Populate move_tactical_events and move_threat_events from existing moves.tactical_flags / threat_awareness JSON';

    private const MOTIF_SEVERITY = [
        'forced_mate_present'    => 'critical',
        'hanging_piece'          => 'major',
        'engine_prefers_capture' => 'major',
        'possible_fork'          => 'minor',
        'possible_pin'           => 'minor',
        'possible_skewer'        => 'minor',
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $query = Game::with('moves')
            ->where('analysis_status', AnalysisStatus::Analysed);

        if ($gameId = $this->option('game')) {
            $query->where('id', $gameId);
        }

        $total    = $query->count();
        $tactical = 0;
        $threat   = 0;
        $errors   = 0;

        $this->info("Backfilling move events for {$total} game(s)" . ($dryRun ? ' [DRY RUN]' : '') . '...');

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(100, function ($games) use ($dryRun, &$tactical, &$threat, &$errors, $bar) {
            foreach ($games as $game) {
                try {
                    $moveIds = $game->moves->pluck('id')->all();

                    if (! $dryRun) {
                        MoveTacticalEvent::whereIn('move_id', $moveIds)->delete();
                        MoveThreatEvent::where('game_id', $game->id)->delete();
                    }

                    $prevMove = null;

                    foreach ($game->moves->sortBy('move_number') as $move) {
                        foreach ($move->tactical_flags ?? [] as $motif) {
                            $tactical++;
                            if (! $dryRun) {
                                MoveTacticalEvent::create([
                                    'move_id'          => $move->id,
                                    'motif'            => $motif,
                                    'severity'         => self::MOTIF_SEVERITY[$motif] ?? 'minor',
                                    'confidence'       => $move->threat_awareness['confidence'] ?? 'low',
                                    'evidence_json'    => ['flags_before' => $move->tactical_flags],
                                    'detector_version' => '1.0',
                                ]);
                            }
                        }

                        $awareness = $move->threat_awareness;
                        if (! empty($awareness['threats_before'])) {
                            $threat++;
                            if (! $dryRun) {
                                MoveThreatEvent::create([
                                    'game_id'               => $game->id,
                                    'threat_source_move_id' => $prevMove?->id,
                                    'response_move_id'      => $move->id,
                                    'threat_type'           => $awareness['threats_before'][0] ?? 'unknown',
                                    'threat_level'          => self::MOTIF_SEVERITY[$awareness['threats_before'][0] ?? ''] ?? 'minor',
                                    'response_status'       => $awareness['response'],
                                    'confidence'            => $awareness['confidence'],
                                    'evidence_json'         => $awareness,
                                    'detector_version'      => '1.0',
                                ]);
                            }
                        }

                        $prevMove = $move;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    $this->newLine();
                    $this->warn("Game {$game->id}: {$e->getMessage()}");
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Done. Tactical events: {$tactical}, Threat events: {$threat}, Errors: {$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
