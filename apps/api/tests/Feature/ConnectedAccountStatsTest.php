<?php

namespace Tests\Feature;

use App\Models\ConnectedAccount;
use App\Models\Game;
use App\Models\Move;
use Database\Seeders\DevUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectedAccountStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevUserSeeder::class);
    }

    private function createAccount(array $overrides = []): ConnectedAccount
    {
        return ConnectedAccount::create(array_merge([
            'user_id'              => DevUserSeeder::UUID,
            'platform'             => 'chesscom',
            'username'             => 'TestPlayer',
            'normalised_username'  => 'testplayer',
            'sync_status'          => 'never_synced',
        ], $overrides));
    }

    private function createGame(ConnectedAccount $account, array $overrides = []): Game
    {
        return Game::create(array_merge([
            'connected_account_id' => $account->id,
            'pgn_raw'              => '[White "A"][Black "B"][Result "1-0"] 1.e4 e5 1-0',
            'white_player'         => 'TestPlayer',
            'black_player'         => 'Opponent',
            'result'               => 'white',
            'user_colour'          => 'white',
            'played_at'            => now(),
            'opening_name'         => 'King Pawn Game',
            'eco_code'             => 'C20',
            'move_count'           => 2,
            'analysis_status'      => 'complete',
            'imported_from'        => 'chesscom',
            'share_code'           => substr(md5(uniqid()), 0, 8),
            'blunder_count'        => 0,
            'mistake_count'        => 0,
            'inaccuracy_count'     => 0,
            'opponent_username'    => 'Opponent',
        ], $overrides));
    }

    private function createMove(Game $game, array $overrides = []): Move
    {
        static $counter = 0;
        $counter++;
        return Move::create(array_merge([
            'game_id'     => $game->id,
            'move_number' => $counter,
            'colour'      => 'white',
            'san'         => 'e4',
            'uci'         => 'e2e4',
            'fen_before'  => 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1',
            'fen_after'   => 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq - 0 1',
            'cp_loss'     => 10,
        ], $overrides));
    }

    public function test_returns_404_for_unknown_username(): void
    {
        $this->getJson('/api/v1/connected-accounts/by-username/chesscom/nobody/stats')
            ->assertStatus(404);
    }

    public function test_returns_zeroed_stats_when_no_analysed_games(): void
    {
        $account = $this->createAccount();

        // pending game should not count
        $this->createGame($account, ['analysis_status' => 'pending']);

        $this->getJson('/api/v1/connected-accounts/by-username/chesscom/testplayer/stats')
            ->assertOk()
            ->assertJsonFragment([
                'games_analysed'        => 0,
                'wins'                  => 0,
                'draws'                 => 0,
                'losses'                => 0,
                'avg_cp_loss'           => null,
                'blunders_per_game'     => null,
                'mistakes_per_game'     => null,
                'inaccuracies_per_game' => null,
                'rating_trend'          => [],
                'cp_loss_trend'         => [],
                'blunders_trend'        => [],
                'recent_games'          => [],
                'analysed_counts_by_type' => [
                    'bullet' => 0,
                    'blitz'  => 0,
                    'rapid'  => 0,
                    'daily'  => 0,
                ],
                'recommended_game_type' => null,
            ]);
    }

    public function test_derives_win_loss_draw_from_board_result_and_user_colour(): void
    {
        $account = $this->createAccount();

        // win: white wins, tracked player is white
        $this->createGame($account, ['result' => 'white', 'user_colour' => 'white']);
        // loss: white wins, tracked player is black
        $this->createGame($account, ['result' => 'white', 'user_colour' => 'black']);
        // draw
        $this->createGame($account, ['result' => 'draw', 'user_colour' => 'white']);
        // win: black wins, tracked player is black
        $this->createGame($account, ['result' => 'black', 'user_colour' => 'black']);
        // loss: black wins, tracked player is white
        $this->createGame($account, ['result' => 'black', 'user_colour' => 'white']);

        $this->getJson('/api/v1/connected-accounts/by-username/chesscom/testplayer/stats')
            ->assertOk()
            ->assertJsonFragment([
                'games_analysed' => 5,
                'wins'           => 2,
                'draws'          => 1,
                'losses'         => 2,
            ]);
    }

    public function test_avg_cp_loss_uses_only_tracked_player_colour_moves(): void
    {
        $account = $this->createAccount();
        $game    = $this->createGame($account, ['user_colour' => 'white']);

        // white moves (tracked): cp_loss 20 and 40 → avg 30
        $this->createMove($game, ['colour' => 'white', 'cp_loss' => 20]);
        $this->createMove($game, ['colour' => 'white', 'cp_loss' => 40]);
        // black move (opponent): should be excluded
        $this->createMove($game, ['colour' => 'black', 'cp_loss' => 200]);

        $this->getJson('/api/v1/connected-accounts/by-username/chesscom/testplayer/stats')
            ->assertOk()
            ->assertJsonFragment(['avg_cp_loss' => 30.0]);
    }

    public function test_avg_cp_loss_excludes_games_with_no_analysed_moves(): void
    {
        $account  = $this->createAccount();
        $gameWith = $this->createGame($account, ['user_colour' => 'white']);
        $this->createGame($account, ['user_colour' => 'white']);

        $this->createMove($gameWith, ['colour' => 'white', 'cp_loss' => 50]);
        // gameWithout has no moves → should not skew average toward 0

        $this->getJson('/api/v1/connected-accounts/by-username/chesscom/testplayer/stats')
            ->assertOk()
            ->assertJsonFragment(['avg_cp_loss' => 50.0]);
    }

    public function test_rating_trend_uses_user_rating_after_with_fallback(): void
    {
        $account = $this->createAccount();
        $this->createGame($account, ['user_rating_after' => 1300, 'user_rating_before' => 1280, 'played_at' => now()->subDays(2)]);
        $this->createGame($account, ['user_rating_after' => null,  'user_rating_before' => 1290, 'played_at' => now()->subDays(1)]);
        $this->createGame($account, ['user_rating_after' => null,  'user_rating_before' => null,  'played_at' => now()]); // omitted

        $data = $this->getJson('/api/v1/connected-accounts/by-username/chesscom/testplayer/stats')
            ->assertOk()
            ->json('rating_trend');

        $this->assertCount(2, $data);
        $this->assertEquals(1300, $data[0]['rating']);
        $this->assertEquals(1290, $data[1]['rating']);
    }

    public function test_recent_games_derives_result_from_player_perspective(): void
    {
        $account = $this->createAccount();
        $this->createGame($account, [
            'result'      => 'black',
            'user_colour' => 'black',
            'share_code'  => 'aaaabbbb',
        ]);

        $data = $this->getJson('/api/v1/connected-accounts/by-username/chesscom/testplayer/stats')
            ->assertOk()
            ->json('recent_games');

        $this->assertCount(1, $data);
        $this->assertEquals('WIN', $data[0]['result']);
        $this->assertEquals('aaaabbbb', $data[0]['share_code']);
    }

    public function test_returns_correct_aggregate_stats(): void
    {
        $account = $this->createAccount();
        $this->createGame($account, ['blunder_count' => 2, 'mistake_count' => 1, 'inaccuracy_count' => 3]);
        $this->createGame($account, ['blunder_count' => 0, 'mistake_count' => 3, 'inaccuracy_count' => 1]);

        $this->getJson('/api/v1/connected-accounts/by-username/chesscom/testplayer/stats')
            ->assertOk()
            ->assertJsonFragment([
                'games_analysed'        => 2,
                'blunders_per_game'     => 1.0,
                'mistakes_per_game'     => 2.0,
                'inaccuracies_per_game' => 2.0,
            ]);
    }

    public function test_stats_can_be_filtered_by_timeframe_days(): void
    {
        $account = $this->createAccount();
        $this->createGame($account, [
            'result'    => 'white',
            'user_colour' => 'white',
            'played_at' => now()->subDays(120),
        ]);
        $this->createGame($account, [
            'result'    => 'black',
            'user_colour' => 'white',
            'played_at' => now()->subDays(5),
        ]);

        $this->getJson('/api/v1/connected-accounts/by-username/chesscom/testplayer/stats?days=90')
            ->assertOk()
            ->assertJsonFragment([
                'games_analysed' => 1,
                'wins'           => 0,
                'losses'         => 1,
            ]);

        $this->getJson('/api/v1/connected-accounts/by-username/chesscom/testplayer/stats?days=0')
            ->assertOk()
            ->assertJsonFragment([
                'games_analysed' => 2,
                'wins'           => 1,
                'losses'         => 1,
            ]);
    }

    public function test_stats_rejects_invalid_days_parameter(): void
    {
        $account = $this->createAccount();
        $this->createGame($account);

        $this->getJson('/api/v1/connected-accounts/by-username/chesscom/testplayer/stats?days=45')
            ->assertStatus(422);
    }

    public function test_stats_can_be_filtered_by_game_type(): void
    {
        $account = $this->createAccount();
        $this->createGame($account, ['time_control' => '60', 'result' => 'white', 'user_colour' => 'white']); // bullet win
        $this->createGame($account, ['time_control' => '300+0', 'result' => 'white', 'user_colour' => 'black']); // blitz loss
        $this->createGame($account, ['time_control' => '1/259200', 'result' => 'draw', 'user_colour' => 'white']); // daily draw

        $this->getJson('/api/v1/connected-accounts/by-username/chesscom/testplayer/stats?game_type=blitz')
            ->assertOk()
            ->assertJsonFragment([
                'games_analysed' => 1,
                'wins' => 0,
                'draws' => 0,
                'losses' => 1,
            ]);

        $this->getJson('/api/v1/connected-accounts/by-username/chesscom/testplayer/stats?game_type=daily')
            ->assertOk()
            ->assertJsonFragment([
                'games_analysed' => 1,
                'wins' => 0,
                'draws' => 1,
                'losses' => 0,
            ]);
    }

    public function test_games_endpoint_can_be_filtered_by_game_type(): void
    {
        $account = $this->createAccount();
        $dailyGame = $this->createGame($account, ['time_control' => '1/259200']);
        $this->createGame($account, ['time_control' => '180+2']);

        $response = $this->getJson('/api/v1/connected-accounts/by-username/chesscom/testplayer/games?game_type=daily')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $response);
        $this->assertSame($dailyGame->id, $response[0]['id']);
    }

    public function test_analysed_counts_and_recommended_type_use_time_control_buckets(): void
    {
        $account = $this->createAccount();
        $this->createGame($account, ['time_control' => '60']);
        $this->createGame($account, ['time_control' => '60']);
        for ($i = 0; $i < 3; $i++) {
            $this->createGame($account, ['time_control' => '300+0']);
        }

        $json = $this->getJson('/api/v1/connected-accounts/by-username/chesscom/testplayer/stats')
            ->assertOk()
            ->json();

        $this->assertSame([
            'bullet' => 2,
            'blitz'  => 3,
            'rapid'  => 0,
            'daily'  => 0,
        ], $json['analysed_counts_by_type']);
        $this->assertSame('blitz', $json['recommended_game_type']);
    }

    public function test_recommended_type_breaks_ties_in_fixed_order(): void
    {
        $account = $this->createAccount();
        $this->createGame($account, ['time_control' => '60']);
        $this->createGame($account, ['time_control' => '60']);
        $this->createGame($account, ['time_control' => '300+0']);
        $this->createGame($account, ['time_control' => '300+0']);

        $json = $this->getJson('/api/v1/connected-accounts/by-username/chesscom/testplayer/stats')
            ->assertOk()
            ->json();

        $this->assertSame('bullet', $json['recommended_game_type']);
    }
}
