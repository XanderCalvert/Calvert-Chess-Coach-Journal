<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Move;
use Database\Seeders\DevUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevUserSeeder::class);
    }

    private function createGame(array $overrides = []): Game
    {
        return Game::create(array_merge([
            'user_id'         => DevUserSeeder::UUID,
            'pgn_raw'         => '[White "A"][Black "B"][Result "1-0"] 1.e4 e5 1-0',
            'white_player'    => 'White Player',
            'black_player'    => 'Black Player',
            'result'          => 'white',
            'user_colour'     => 'white',
            'played_at'       => now(),
            'eco_code'        => 'C20',
            'opening_name'    => 'King Pawn Game',
            'move_count'      => 2,
            'analysis_status' => 'pending',
            'imported_from'   => 'paste',
            'share_code'      => 'abc234de',
        ], $overrides));
    }

    public function test_show_returns_expected_contract_and_moves_ordered_by_move_number(): void
    {
        $game = $this->createGame();
        Move::create([
            'game_id' => $game->id,
            'move_number' => 2,
            'colour' => 'black',
            'san' => 'e5',
            'uci' => 'e7e5',
            'fen_before' => 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq - 0 1',
            'fen_after' => 'rnbqkbnr/pppp1ppp/8/4p3/4P3/8/PPPP1PPP/RNBQKBNR w KQkq - 0 2',
        ]);
        Move::create([
            'game_id' => $game->id,
            'move_number' => 1,
            'colour' => 'white',
            'san' => 'e4',
            'uci' => 'e2e4',
            'fen_before' => 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1',
            'fen_after' => 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq - 0 1',
        ]);

        $response = $this->getJson("/api/v1/games/{$game->id}")
            ->assertStatus(200);

        $response->assertJsonStructure([
            'id',
            'white_player',
            'black_player',
            'result',
            'played_at',
            'eco_code',
            'opening_name',
            'move_count',
            'analysis_status',
            'accuracy_pct',
            'blunder_count',
            'mistake_count',
            'inaccuracy_count',
            'user_colour',
            'share_code',
            'moves' => [[
                'id',
                'move_number',
                'colour',
                'san',
                'uci',
                'fen_before',
                'fen_after',
                'cp_score',
                'cp_loss',
                'classification',
            ]],
        ]);

        $moves = $response->json('moves');
        $this->assertCount(2, $moves);
        $this->assertSame(1, $moves[0]['move_number']);
        $this->assertSame(2, $moves[1]['move_number']);
    }

    public function test_show_returns_404_for_unknown_uuid(): void
    {
        $this->getJson('/api/v1/games/00000000-0000-0000-0000-000000000000')
            ->assertStatus(404);
    }

    public function test_show_by_share_code_returns_same_game(): void
    {
        $game = $this->createGame(['share_code' => 'm8n7p6q5']);

        $this->getJson('/api/v1/games/by-share-code/m8n7p6q5')
            ->assertStatus(200)
            ->assertJson([
                'id' => $game->id,
                'share_code' => 'm8n7p6q5',
            ]);
    }

    public function test_show_by_share_code_includes_chess_com_source_url_when_present(): void
    {
        $game = $this->createGame([
            'imported_from' => 'chesscom',
            'pgn_raw' => '[Event "Live Chess"] [Link "https://www.chess.com/game/live/123456789"] 1.e4 e5 1-0',
        ]);

        $this->getJson("/api/v1/games/by-share-code/{$game->share_code}")
            ->assertStatus(200)
            ->assertJson([
                'source_url' => 'https://www.chess.com/game/live/123456789',
            ]);
    }

    public function test_show_by_share_code_uses_link_header_not_site_label_for_chess_com_source_url(): void
    {
        $pgn = <<<'PGN'
[Event "Live Chess"]
[Site "Chess.com"]
[Link "https://www.chess.com/game/live/168381943790?move=0"]
1. e4 e5 1-0
PGN;

        $game = $this->createGame([
            'imported_from' => 'chesscom',
            'pgn_raw' => $pgn,
        ]);

        $this->getJson("/api/v1/games/by-share-code/{$game->share_code}")
            ->assertStatus(200)
            ->assertJson([
                'source_url' => 'https://www.chess.com/game/live/168381943790?move=0',
            ]);
    }

    public function test_show_by_share_code_returns_404_when_not_found(): void
    {
        $this->getJson('/api/v1/games/by-share-code/zzzzzzzz')
            ->assertStatus(404);
    }

    public function test_share_code_lookup_is_case_sensitive(): void
    {
        $this->createGame(['share_code' => 'abcd2345']);

        $this->getJson('/api/v1/games/by-share-code/ABCD2345')
            ->assertStatus(404);
    }
}
