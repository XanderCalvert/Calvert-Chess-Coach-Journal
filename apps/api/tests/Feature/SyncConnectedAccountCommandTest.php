<?php

namespace Tests\Feature;

use App\Enums\Platform;
use App\Enums\SyncStatus;
use App\Jobs\ImportExternalGameJob;
use App\Jobs\SyncChessComAccountJob;
use App\Models\ConnectedAccount;
use Database\Seeders\DevUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncConnectedAccountCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevUserSeeder::class);
    }

    private function chessComGameStub(string $uuid): array
    {
        return [
            'uuid'  => $uuid,
            'pgn'   => '[White "A"][Black "B"][Result "1-0"] 1.e4 e5 1-0',
            'white' => ['username' => 'A', 'rating' => 1500, 'result' => 'win'],
            'black' => ['username' => 'B', 'rating' => 1500, 'result' => 'lose'],
        ];
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $monthsGamesByUrl
     */
    private function fakeChessComArchives(string $player, array $monthsGamesByUrl): void
    {
        $statsPayload = [
            'chess_rapid'  => ['last' => ['rating' => 1500]],
            'chess_blitz'  => ['last' => ['rating' => 1500]],
            'chess_bullet' => ['last' => ['rating' => 1500]],
            'chess_daily'  => ['last' => ['rating' => 1500]],
        ];

        $base = "https://api.chess.com/pub/player/{$player}";
        $fakes = [
            "{$base}/stats"           => Http::response($statsPayload, 200),
            "{$base}/games/archives"  => Http::response(['archives' => array_keys($monthsGamesByUrl)], 200),
        ];

        foreach ($monthsGamesByUrl as $archiveUrl => $games) {
            $fakes[$archiveUrl] = Http::response(['games' => $games], 200);
        }

        Http::fake($fakes);
    }

    public function test_full_archive_queues_all_games_across_months(): void
    {
        Queue::fake();

        $urlOld = 'https://api.chess.com/pub/player/testuser/games/2020/01';
        $urlNew = 'https://api.chess.com/pub/player/testuser/games/2024/06';

        $oldGames = [
            $this->chessComGameStub('old-1'),
            $this->chessComGameStub('old-2'),
        ];
        $newGames = [
            $this->chessComGameStub('new-1'),
            $this->chessComGameStub('new-2'),
            $this->chessComGameStub('new-3'),
        ];

        $this->fakeChessComArchives('testuser', [
            $urlOld => $oldGames,
            $urlNew => $newGames,
        ]);

        $account = ConnectedAccount::create([
            'user_id'             => DevUserSeeder::UUID,
            'platform'            => Platform::Chesscom,
            'username'            => 'testuser',
            'normalised_username' => 'testuser',
            'sync_status'         => SyncStatus::Syncing,
        ]);

        $job = new SyncChessComAccountJob($account->id, true);
        $this->app->call([$job, 'handle']);

        Queue::assertPushed(ImportExternalGameJob::class, 5);
        $account->refresh();
        $this->assertSame(SyncStatus::Synced, $account->sync_status);
    }

    public function test_recent_window_caps_at_twenty_from_newest_months(): void
    {
        Queue::fake();

        $urlOld = 'https://api.chess.com/pub/player/testuser/games/2020/01';
        $urlNew = 'https://api.chess.com/pub/player/testuser/games/2024/06';

        $oldGames = [];
        for ($i = 0; $i < 5; $i++) {
            $oldGames[] = $this->chessComGameStub("old-{$i}");
        }
        $newGames = [];
        for ($i = 0; $i < 25; $i++) {
            $newGames[] = $this->chessComGameStub("new-{$i}");
        }

        $this->fakeChessComArchives('testuser', [
            $urlOld => $oldGames,
            $urlNew => $newGames,
        ]);

        $account = ConnectedAccount::create([
            'user_id'             => DevUserSeeder::UUID,
            'platform'            => Platform::Chesscom,
            'username'            => 'testuser',
            'normalised_username' => 'testuser',
            'sync_status'         => SyncStatus::Syncing,
        ]);

        $job = new SyncChessComAccountJob($account->id, false);
        $this->app->call([$job, 'handle']);

        Queue::assertPushed(ImportExternalGameJob::class, 20);
    }

    public function test_command_requires_account_without_create(): void
    {
        $this->artisan('chess:sync-connected-account', ['platform' => 'chesscom', 'username' => 'nobody'])
            ->assertFailed();
    }

    public function test_command_create_option_inserts_row_and_dispatches_job(): void
    {
        Queue::fake();

        $url = 'https://api.chess.com/pub/player/newbie/games/2024/01';
        $this->fakeChessComArchives('newbie', [
            $url => [$this->chessComGameStub('only-one')],
        ]);

        $this->artisan('chess:sync-connected-account', [
            'platform' => 'chesscom',
            'username' => 'Newbie',
            '--create' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('connected_accounts', [
            'normalised_username' => 'newbie',
            'platform'            => 'chesscom',
        ]);

        Queue::assertPushed(SyncChessComAccountJob::class);
    }
}
