<?php

namespace Tests\Feature;

use App\Jobs\AnalyseGameJob;
use App\Models\Game;
use Database\Seeders\DevUserSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Move;
use Tests\TestCase;

class GameImportTest extends TestCase
{
    use RefreshDatabase;

    private const STARTING_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

    // FEN after 1.e4 e5 2.Nf3 (3 half-moves)
    private const TERMINAL_FEN = 'rnbqkbnr/pppp1ppp/8/4p3/4P3/5N2/PPPP1PPP/RNBQKB1R b KQkq - 1 2';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevUserSeeder::class);
        Bus::fake();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'pgn_raw'      => '[White "Paul Morphy"][Black "Duke of Brunswick"][Result "1-0"] 1.e4 e5 2.Nf3 1-0',
            'white_player' => 'Paul Morphy',
            'black_player' => 'Duke of Brunswick',
            'result'       => 'white',
            'eco_code'     => 'C41',
            'opening_name' => 'Philidor Defense',
            'move_count'   => 3,
            'moves'        => [
                [
                    'move_number' => 1,
                    'colour'      => 'white',
                    'san'         => 'e4',
                    'uci'         => 'e2e4',
                    'fen_before'  => self::STARTING_FEN,
                    'fen_after'   => 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq - 0 1',
                ],
                [
                    'move_number' => 2,
                    'colour'      => 'black',
                    'san'         => 'e5',
                    'uci'         => 'e7e5',
                    'fen_before'  => 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq - 0 1',
                    'fen_after'   => 'rnbqkbnr/pppp1ppp/8/4p3/4P3/8/PPPP1PPP/RNBQKBNR w KQkq - 0 2',
                ],
                [
                    'move_number' => 3,
                    'colour'      => 'white',
                    'san'         => 'Nf3',
                    'uci'         => 'g1f3',
                    'fen_before'  => 'rnbqkbnr/pppp1ppp/8/4p3/4P3/8/PPPP1PPP/RNBQKBNR w KQkq - 0 2',
                    'fen_after'   => self::TERMINAL_FEN,
                ],
            ],
        ], $overrides);
    }

    public function test_creates_game_and_moves_and_returns_201(): void
    {
        $response = $this->postJson('/api/v1/games', $this->validPayload());

        $response->assertStatus(201)
                 ->assertJsonStructure(['game_id', 'move_count'])
                 ->assertJson(['move_count' => 3]);
    }

    public function test_persists_correct_move_count(): void
    {
        $response = $this->postJson('/api/v1/games', $this->validPayload());
        $gameId = $response->json('game_id');

        $this->assertSame(3, Move::where('game_id', $gameId)->count());
    }

    public function test_first_move_fen_before_is_starting_position(): void
    {
        $response = $this->postJson('/api/v1/games', $this->validPayload());
        $gameId = $response->json('game_id');

        $first = Move::where('game_id', $gameId)->where('move_number', 1)->firstOrFail();
        $this->assertSame(self::STARTING_FEN, $first->fen_before);
    }

    public function test_last_move_fen_after_matches_terminal_position(): void
    {
        $response = $this->postJson('/api/v1/games', $this->validPayload());
        $gameId = $response->json('game_id');

        $last = Move::where('game_id', $gameId)->where('move_number', 3)->firstOrFail();
        $this->assertSame(self::TERMINAL_FEN, $last->fen_after);
    }

    public function test_missing_required_fields_returns_422(): void
    {
        $this->postJson('/api/v1/games', [])->assertStatus(422);
    }

    public function test_invalid_result_enum_returns_422(): void
    {
        $this->postJson('/api/v1/games', $this->validPayload(['result' => '1-0']))
             ->assertStatus(422);
    }

    public function test_invalid_move_colour_returns_422(): void
    {
        $payload = $this->validPayload();
        $payload['moves'][0]['colour'] = 'w';

        $this->postJson('/api/v1/games', $payload)->assertStatus(422);
    }

    public function test_invalid_uci_format_returns_422(): void
    {
        $payload = $this->validPayload();
        $payload['moves'][0]['uci'] = 'e4';

        $this->postJson('/api/v1/games', $payload)->assertStatus(422);
    }

    public function test_empty_moves_array_returns_422(): void
    {
        $this->postJson('/api/v1/games', $this->validPayload(['moves' => []]))->assertStatus(422);
    }

    public function test_dispatches_analyse_game_job_after_import(): void
    {
        $response = $this->postJson('/api/v1/games', $this->validPayload())
            ->assertStatus(201);

        $gameId = $response->json('game_id');

        Bus::assertDispatched(AnalyseGameJob::class, function (AnalyseGameJob $job) use ($gameId): bool {
            return $job->gameId === $gameId && $job->force === false;
        });
    }

    public function test_uses_defaults_when_optional_fields_are_missing(): void
    {
        $payload = $this->validPayload();
        unset($payload['eco_code'], $payload['opening_name'], $payload['move_count'], $payload['played_at']);

        $response = $this->postJson('/api/v1/games', $payload)->assertStatus(201);
        $game = Game::findOrFail($response->json('game_id'));

        $this->assertSame('', $game->eco_code);
        $this->assertSame('Unknown', $game->opening_name);
        $this->assertSame(count($payload['moves']), $game->move_count);
        $this->assertSame('pending', $game->analysis_status->value);
        $this->assertSame('paste', $game->imported_from->value);
        $this->assertSame('white', $game->user_colour->value);
        $this->assertNotNull($game->share_code);
        $this->assertMatchesRegularExpression('/^[abcdefghjkmnpqrstuvwxyz23456789]{8}$/', $game->share_code);
    }

    public function test_missing_required_fields_returns_field_errors(): void
    {
        $this->postJson('/api/v1/games', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'pgn_raw',
                'white_player',
                'black_player',
                'result',
                'moves',
            ]);
    }

    public function test_invalid_move_fields_return_field_errors(): void
    {
        $payload = $this->validPayload();
        $payload['moves'][0]['colour'] = 'w';
        $payload['moves'][0]['uci'] = 'e4';
        $payload['moves'][0]['san'] = str_repeat('N', 11);

        $this->postJson('/api/v1/games', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'moves.0.colour',
                'moves.0.uci',
                'moves.0.san',
            ]);
    }
}
