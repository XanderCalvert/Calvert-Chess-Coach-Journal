<?php

namespace App\Console\Commands;

use App\Models\Move;
use App\Services\BoardAnalysisService;
use App\Services\CoachingTemplateService;
use App\Services\FenParserService;
use App\Services\MoveThemeExtractorService;
use App\Services\ThreatDetectorService;
use Illuminate\Console\Command;

class BackfillCoachingData extends Command
{
    protected $signature = 'coaching:backfill
        {--game= : Limit to a specific game ID}
        {--version=1 : Backfill moves with coaching_version less than this value}
        {--dry-run : Preview without writing}';

    protected $description = 'Populate coaching columns on existing analysed moves (no Stockfish calls)';

    public function handle(): int
    {
        $fenParser      = new FenParserService();
        $boardAnalysis  = new BoardAnalysisService($fenParser);
        $themeExtractor = new MoveThemeExtractorService($fenParser, $boardAnalysis);
        $threatDetector = new ThreatDetectorService($fenParser, $boardAnalysis);
        $templateService = new CoachingTemplateService();

        $version = (int) $this->option('version');
        $dryRun  = $this->option('dry-run');

        $query = Move::with('engineAnalysis')
            ->whereNotNull('classification')
            ->where(fn ($q) => $q
                ->whereNull('coaching_version')
                ->orWhere('coaching_version', '<', $version)
            );

        if ($gameId = $this->option('game')) {
            $query->where('game_id', $gameId);
        }

        $total   = $query->count();
        $updated = 0;
        $errors  = 0;

        $this->info("Backfilling coaching data for {$total} move(s)" . ($dryRun ? ' [DRY RUN]' : '') . '...');

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(200, function ($moves) use (
            $themeExtractor, $threatDetector, $templateService,
            $dryRun, $version, &$updated, &$errors, $bar
        ) {
            foreach ($moves as $move) {
                try {
                    $engine = $move->engineAnalysis;

                    // Build minimal engine data from stored EngineAnalysis — no Stockfish call
                    $engineBefore = [
                        'best_move' => $engine?->best_move_uci ?? '',
                        'cp'        => $engine?->cp_evaluation ?? 0,
                        'mate'      => null,
                        'best_line' => $engine?->best_line ?? [],
                    ];
                    // We don't store the after-position engine data, so provide empty defaults
                    $engineAfter = ['best_move' => '', 'cp' => 0, 'mate' => null, 'best_line' => []];

                    $themes     = $themeExtractor->extract($move->fen_before, $move->fen_after, $move->uci, $move->move_number, $move->colour->value);
                    $threatData = $threatDetector->analyse($move->fen_before, $move->fen_after, $move->uci, $engineBefore, $engineAfter);
                    $riskNote   = $templateService->buildRiskNote($move->classification, $themes, $threatData['tactical_flags'], $threatData['threat_awareness']);

                    if (!$dryRun) {
                        $move->update([
                            'themes'           => $themes,
                            'tactical_flags'   => $threatData['tactical_flags'],
                            'threat_awareness' => $threatData['threat_awareness'],
                            'risk_note'        => $riskNote,
                            'coaching_version' => $version,
                        ]);
                    }

                    $updated++;
                } catch (\Throwable $e) {
                    $errors++;
                    $this->newLine();
                    $this->warn("Move {$move->id}: {$e->getMessage()}");
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Done. Updated: {$updated}, Errors: {$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
