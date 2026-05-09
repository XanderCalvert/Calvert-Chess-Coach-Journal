<?php

namespace Tests\Unit;

use App\Enums\MoveClassification;
use App\Services\BoardAnalysisService;
use App\Services\CoachingTemplateService;
use App\Services\FenParserService;
use App\Services\MoveThemeExtractorService;
use App\Services\ThreatDetectorService;
use Tests\TestCase;

class CoachingPayloadShapeTest extends TestCase
{
    private const ALLOWED_TACTICAL_FLAGS = [
        'forced_mate_present',
        'engine_prefers_capture',
        'hanging_piece',
        'possible_fork',
        'possible_pin',
        'possible_skewer',
    ];

    private const ALLOWED_RESPONSES   = ['addressed', 'not_addressed', 'unknown', 'none'];
    private const ALLOWED_CONFIDENCES = ['low', 'medium', 'high'];

    private MoveThemeExtractorService $themeExtractor;
    private ThreatDetectorService $threatDetector;
    private CoachingTemplateService $templateService;

    protected function setUp(): void
    {
        parent::setUp();
        $parser = new FenParserService();
        $board  = new BoardAnalysisService($parser);
        $this->themeExtractor  = new MoveThemeExtractorService($parser, $board);
        $this->threatDetector  = new ThreatDetectorService($parser, $board);
        $this->templateService = new CoachingTemplateService();
    }

    public function test_payload_shape_for_quiet_move(): void
    {
        $fenBefore = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
        $fenAfter  = 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1';
        $engineBefore = ['best_move' => 'e2e4', 'cp' => 20, 'mate' => null, 'best_line' => ['e2e4']];
        $engineAfter  = ['best_move' => 'e7e5', 'cp' => -15, 'mate' => null, 'best_line' => ['e7e5']];

        $themes     = $this->themeExtractor->extract($fenBefore, $fenAfter, 'e2e4', 1, 'white');
        $threatData = $this->threatDetector->analyse($fenBefore, $fenAfter, 'e2e4', $engineBefore, $engineAfter);
        $riskNote   = $this->templateService->buildRiskNote(MoveClassification::Best, $themes, $threatData['tactical_flags'], $threatData['threat_awareness']);

        $this->assertIsArray($themes);
        foreach ($themes as $theme) {
            $this->assertIsString($theme);
        }

        $this->assertIsArray($threatData['tactical_flags']);
        foreach ($threatData['tactical_flags'] as $flag) {
            $this->assertContains($flag, self::ALLOWED_TACTICAL_FLAGS, "Unexpected tactical flag: {$flag}");
        }

        $awareness = $threatData['threat_awareness'];
        $this->assertIsArray($awareness);
        $this->assertArrayHasKey('threats_before', $awareness);
        $this->assertArrayHasKey('threats_after', $awareness);
        $this->assertArrayHasKey('response', $awareness);
        $this->assertArrayHasKey('confidence', $awareness);
        $this->assertIsArray($awareness['threats_before']);
        $this->assertIsArray($awareness['threats_after']);
        $this->assertContains($awareness['response'], self::ALLOWED_RESPONSES);
        $this->assertContains($awareness['confidence'], self::ALLOWED_CONFIDENCES);

        // Best move with no flags — no risk note expected
        $this->assertNull($riskNote);
    }

    public function test_risk_note_is_null_or_non_empty_string(): void
    {
        $fenBefore = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
        $fenAfter  = 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1';
        $engineBefore = ['best_move' => 'd2d4', 'cp' => 500, 'mate' => null, 'best_line' => ['d2d4']];
        $engineAfter  = ['best_move' => 'e7e5', 'cp' => -10, 'mate' => null, 'best_line' => ['e7e5']];

        foreach (MoveClassification::cases() as $classification) {
            $threatData = $this->threatDetector->analyse($fenBefore, $fenAfter, 'e2e4', $engineBefore, $engineAfter);
            $riskNote   = $this->templateService->buildRiskNote($classification, [], $threatData['tactical_flags'], $threatData['threat_awareness']);

            $this->assertTrue(
                $riskNote === null || (is_string($riskNote) && strlen($riskNote) > 0),
                "risk_note should be null or a non-empty string for classification {$classification->value}"
            );
        }
    }

    public function test_forced_mate_present_flag_set_when_engine_reports_mate(): void
    {
        $fenBefore = '8/8/8/8/8/8/6QK/7k w - - 0 1';
        $fenAfter  = '8/8/8/8/8/8/6QK/6Kk b - - 1 1';
        $engineBefore = ['best_move' => 'g2g1', 'cp' => null, 'mate' => 1, 'best_line' => ['g2g1']];
        $engineAfter  = ['best_move' => '', 'cp' => 0, 'mate' => null, 'best_line' => []];

        $threatData = $this->threatDetector->analyse($fenBefore, $fenAfter, 'h2g1', $engineBefore, $engineAfter);

        $this->assertContains('forced_mate_present', $threatData['tactical_flags']);
    }

    public function test_no_unknown_flags_in_payload(): void
    {
        $fenBefore = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
        $fenAfter  = 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1';
        $engineBefore = ['best_move' => 'e2e4', 'cp' => 20, 'mate' => null, 'best_line' => ['e2e4', 'e7e5']];
        $engineAfter  = ['best_move' => 'e7e5', 'cp' => -20, 'mate' => null, 'best_line' => ['e7e5']];

        $threatData = $this->threatDetector->analyse($fenBefore, $fenAfter, 'e2e4', $engineBefore, $engineAfter);

        foreach ($threatData['tactical_flags'] as $flag) {
            $this->assertContains($flag, self::ALLOWED_TACTICAL_FLAGS, "Unexpected flag: {$flag}");
        }
    }
}
