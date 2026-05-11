<?php

namespace Tests\Feature;

use App\Enums\ExplanationStatus;
use App\Enums\GamePhase;
use App\Models\Game;
use App\Models\KeyMoment;
use App\Models\Move;
use App\Models\User;
use Database\Seeders\DevUserSeeder;
use Database\Seeders\MistakeTagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameShowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevUserSeeder::class);
        $this->seed(MistakeTagSeeder::class);
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    }

    private function createGame(array $overrides = []): Game
    {
        return Game::create(array_merge([
            'user_id'         => $this->user->id,
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
                'themes',
                'tactical_flags',
                'risk_note',
            ]],
        ]);

        $moves = $response->json('moves');
        $this->assertCount(2, $moves);
        $this->assertSame(1, $moves[0]['move_number']);
        $this->assertSame(2, $moves[1]['move_number']);

        // Coaching fields should be present (null values are valid for un-analysed moves)
        foreach ($moves as $move) {
            $this->assertArrayHasKey('themes', $move);
            $this->assertArrayHasKey('tactical_flags', $move);
            $this->assertArrayHasKey('threat_awareness', $move);
            $this->assertArrayHasKey('risk_note', $move);
            $this->assertIsArray($move['themes']);
            $this->assertIsArray($move['tactical_flags']);
        }
    }

    public function test_show_returns_404_for_unknown_uuid(): void
    {
        $this->getJson('/api/v1/games/00000000-0000-0000-0000-000000000000')
            ->assertStatus(404);
    }

    public function test_show_returns_404_for_another_users_game(): void
    {
        $otherUser = User::factory()->create();
        $game = Game::create([
            'user_id'         => $otherUser->id,
            'pgn_raw'         => '[White "A"][Black "B"][Result "1-0"] 1.e4 e5 1-0',
            'white_player'    => 'Other',
            'black_player'    => 'Player',
            'result'          => 'white',
            'user_colour'     => 'white',
            'played_at'       => now(),
            'eco_code'        => 'C20',
            'opening_name'    => 'King Pawn Game',
            'move_count'      => 2,
            'analysis_status' => 'pending',
            'imported_from'   => 'paste',
            'share_code'      => 'other001',
        ]);

        $this->getJson("/api/v1/games/{$game->id}")->assertStatus(404);
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

    public function test_response_includes_empty_key_moments_when_none_exist(): void
    {
        $game = $this->createGame(['share_code' => 'km000001']);

        $response = $this->getJson("/api/v1/games/by-share-code/{$game->share_code}")
            ->assertStatus(200);

        $this->assertSame([], $response->json('key_moments'));
    }

    public function test_response_includes_key_moments_with_expected_shape(): void
    {
        $game = $this->createGame(['share_code' => 'km000002', 'analysis_status' => 'analysed']);

        $move = Move::create([
            'game_id'     => $game->id,
            'move_number' => 12,
            'colour'      => 'white',
            'san'         => 'Nf6',
            'uci'         => 'g8f6',
            'fen_before'  => 'start',
            'fen_after'   => 'after',
            'classification' => 'blunder',
            'cp_loss'     => 320,
            'risk_note'   => 'A piece is hanging.',
        ]);

        KeyMoment::create([
            'game_id'            => $game->id,
            'move_id'            => $move->id,
            'rank'               => 1,
            'cp_loss'            => 320,
            'game_phase'         => GamePhase::Middlegame,
            'explanation_status' => ExplanationStatus::NotRequested,
        ]);

        $response = $this->getJson("/api/v1/games/by-share-code/km000002")
            ->assertStatus(200);

        $keyMoments = $response->json('key_moments');
        $this->assertCount(1, $keyMoments);

        $km = $keyMoments[0];
        $this->assertSame(1,           $km['rank']);
        $this->assertSame(12,          $km['move_number']);
        $this->assertSame('white',     $km['colour']);
        $this->assertSame('Nf6',       $km['san']);
        $this->assertSame(320,         $km['cp_loss']);
        $this->assertSame('blunder',   $km['classification']);
        $this->assertSame('middlegame', $km['game_phase']);
        $this->assertSame('A piece is hanging.', $km['risk_note']);
        $this->assertArrayHasKey('best_move_uci',    $km);
        $this->assertArrayHasKey('best_move_san',    $km);
        $this->assertArrayHasKey('explanation_text', $km);
    }

    public function test_key_moments_are_ordered_by_rank(): void
    {
        $game = $this->createGame(['share_code' => 'km000003', 'analysis_status' => 'analysed']);

        $move1 = Move::create(['game_id' => $game->id, 'move_number' => 5,  'colour' => 'white', 'san' => 'e4', 'uci' => 'e2e4', 'fen_before' => 'a', 'fen_after' => 'b', 'cp_loss' => 400]);
        $move2 = Move::create(['game_id' => $game->id, 'move_number' => 10, 'colour' => 'black', 'san' => 'd5', 'uci' => 'd7d5', 'fen_before' => 'c', 'fen_after' => 'd', 'cp_loss' => 250]);

        KeyMoment::create(['game_id' => $game->id, 'move_id' => $move2->id, 'rank' => 2, 'cp_loss' => 250, 'game_phase' => GamePhase::Middlegame, 'explanation_status' => ExplanationStatus::NotRequested]);
        KeyMoment::create(['game_id' => $game->id, 'move_id' => $move1->id, 'rank' => 1, 'cp_loss' => 400, 'game_phase' => GamePhase::Opening,    'explanation_status' => ExplanationStatus::NotRequested]);

        $keyMoments = $this->getJson("/api/v1/games/by-share-code/km000003")
            ->assertStatus(200)
            ->json('key_moments');

        $this->assertSame(1, $keyMoments[0]['rank']);
        $this->assertSame(2, $keyMoments[1]['rank']);
    }
}
