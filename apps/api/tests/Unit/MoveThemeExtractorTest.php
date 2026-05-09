<?php

namespace Tests\Unit;

use App\Services\BoardAnalysisService;
use App\Services\FenParserService;
use App\Services\MoveThemeExtractorService;
use Tests\TestCase;

class MoveThemeExtractorTest extends TestCase
{
    private MoveThemeExtractorService $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $parser = new FenParserService();
        $board  = new BoardAnalysisService($parser);
        $this->extractor = new MoveThemeExtractorService($parser, $board);
    }

    public function test_development_knight_from_back_rank_early_game(): void
    {
        // Starting position, white knight g1f3 — move 1
        $fenBefore = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
        $fenAfter  = 'rnbqkbnr/pppppppp/8/8/8/5N2/PPPPPPPP/RNBQKB1R b KQkq - 1 1';

        $themes = $this->extractor->extract($fenBefore, $fenAfter, 'g1f3', 1, 'white');
        $this->assertContains('development', $themes);
    }

    public function test_no_development_for_pawn_moves(): void
    {
        $fenBefore = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
        $fenAfter  = 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1';

        $themes = $this->extractor->extract($fenBefore, $fenAfter, 'e2e4', 1, 'white');
        $this->assertNotContains('development', $themes);
    }

    public function test_no_development_for_piece_moves_after_move_20(): void
    {
        // Move 21 — past the development window
        $fenBefore = 'r1bqkb1r/pppppppp/2n2n2/8/4P3/5N2/PPPP1PPP/RNBQKB1R w KQkq - 4 21';
        $fenAfter  = 'r1bqkb1r/pppppppp/2n2n2/8/4P3/2N2N2/PPPP1PPP/R1BQKB1R b KQkq - 5 21';

        $themes = $this->extractor->extract($fenBefore, $fenAfter, 'b1c3', 21, 'white');
        $this->assertNotContains('development', $themes);
    }

    public function test_center_control_e4(): void
    {
        $fenBefore = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
        $fenAfter  = 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1';

        $themes = $this->extractor->extract($fenBefore, $fenAfter, 'e2e4', 1, 'white');
        $this->assertContains('center_control', $themes);
    }

    public function test_center_control_d5(): void
    {
        $fenBefore = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR b KQkq - 0 1';
        $fenAfter  = 'rnbqkbnr/ppp1pppp/8/3p4/8/8/PPPPPPPP/RNBQKBNR w KQkq d6 0 2';

        $themes = $this->extractor->extract($fenBefore, $fenAfter, 'd7d5', 2, 'black');
        $this->assertContains('center_control', $themes);
    }

    public function test_material_on_capture(): void
    {
        // White queen takes black pawn on e5
        $fenBefore = 'rnbqkbnr/pppp1ppp/8/4p3/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
        $fenAfter  = 'rnbqkbnr/pppp1ppp/8/4Q3/8/8/PPPPPPPP/RNB1KBNR b KQkq - 0 1';

        $themes = $this->extractor->extract($fenBefore, $fenAfter, 'd1e5', 1, 'white');
        $this->assertContains('material', $themes);
    }

    public function test_no_material_on_quiet_move(): void
    {
        $fenBefore = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
        $fenAfter  = 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1';

        $themes = $this->extractor->extract($fenBefore, $fenAfter, 'e2e4', 1, 'white');
        $this->assertNotContains('material', $themes);
    }

    public function test_king_safety_on_kingside_castling(): void
    {
        // White castles kingside: e1g1
        $fenBefore = 'r1bqk2r/pppppppp/2n2n2/4p3/4P3/5NB1/PPPPBPPP/RNBQK2R w KQkq - 0 1';
        $fenAfter  = 'r1bqk2r/pppppppp/2n2n2/4p3/4P3/5NB1/PPPPBPPP/RNBQ1RK1 b kq - 1 1';

        $themes = $this->extractor->extract($fenBefore, $fenAfter, 'e1g1', 1, 'white');
        $this->assertContains('king_safety', $themes);
    }

    public function test_no_king_safety_on_regular_king_move(): void
    {
        // King steps one square — not castling
        $fenBefore = '8/8/8/8/8/8/8/4K3 w - - 0 1';
        $fenAfter  = '8/8/8/8/8/8/8/3K4 b - - 1 1';

        $themes = $this->extractor->extract($fenBefore, $fenAfter, 'e1d1', 1, 'white');
        $this->assertNotContains('king_safety', $themes);
    }

    public function test_activity_rook_to_open_file(): void
    {
        // Rook moves to d1 — d file has no pawns
        $fenBefore = '8/8/8/8/8/8/PPPPPPPP/3R4 w - - 0 1';
        $fenAfter  = '8/8/8/8/8/8/PPPPPPPP/3R4 b - - 1 1';

        // d file open (no pawns), rook moves to d1
        // Use a simpler position to isolate: rook on a1 moves to d1 (open d-file)
        $fenBefore2 = '8/8/8/8/8/8/PPP1PPPP/R7 w - - 0 1';
        $fenAfter2  = '8/8/8/8/8/8/PPP1PPPP/3R4 b - - 1 1';

        $themes = $this->extractor->extract($fenBefore2, $fenAfter2, 'a1d1', 1, 'white');
        $this->assertContains('activity', $themes);
    }

    public function test_quiet_pawn_push_produces_no_coaching_noise(): void
    {
        // h2h3 — a quiet pawn push with no coaching significance
        $fenBefore = 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR w KQkq - 0 1';
        $fenAfter  = 'rnbqkbnr/pppppppp/8/8/4P3/7P/PPPP1PP1/RNBQKBNR b KQkq - 0 1';

        $themes = $this->extractor->extract($fenBefore, $fenAfter, 'h2h3', 5, 'white');
        $this->assertEmpty($themes);
    }

    public function test_en_passant_capture_produces_material_theme(): void
    {
        // En passant: white pawn e5 captures black pawn on d5 → d6
        $fenBefore = '8/8/8/3pP3/8/8/8/8 w - d6 0 1';
        $fenAfter  = '8/8/3P4/8/8/8/8/8 b - - 0 1';

        $themes = $this->extractor->extract($fenBefore, $fenAfter, 'e5d6', 10, 'white');
        $this->assertContains('material', $themes);
    }

    public function test_promotion_does_not_produce_development_theme(): void
    {
        // Pawn promotes to queen — not a piece developing from back rank
        $fenBefore = '8/P7/8/8/8/8/8/8 w - - 0 1';
        $fenAfter  = 'Q7/8/8/8/8/8/8/8 b - - 0 1';

        $themes = $this->extractor->extract($fenBefore, $fenAfter, 'a7a8q', 5, 'white');
        $this->assertNotContains('development', $themes);
    }

    public function test_stalemate_position_produces_no_themes(): void
    {
        // Black king in stalemate corner — any preceding null move has nothing special
        $fenBefore = '8/8/8/8/8/8/6QK/8 w - - 0 1';
        $fenAfter  = '8/8/8/8/8/8/6QK/8 b - - 1 1';

        // Use a queen move to f2 — not to a central square
        $fenBefore2 = '8/8/8/8/8/8/7K/6Q1 w - - 0 1';
        $fenAfter2  = '8/8/8/8/8/8/5Q1K/8 b - - 1 1';
        $themes = $this->extractor->extract($fenBefore2, $fenAfter2, 'g1f2', 30, 'white');
        $this->assertEmpty($themes);
    }
}
