<?php

namespace Tests\Feature;

use App\Models\Game;
use Database\Seeders\DevUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShareCodeBackfillMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevUserSeeder::class);
    }

    public function test_backfill_migration_replaces_non_8_char_share_codes_postgres_only(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Share code type-alter SQL in backfill migration is Postgres-specific');
        }

        $game = Game::factory()->create([
            'user_id' => DevUserSeeder::UUID,
            'share_code' => null,
        ]);

        DB::table('games')->where('id', $game->id)->update(['share_code' => 'abc123']);

        $migration = require base_path('database/migrations/2026_05_08_000004_backfill_share_codes_to_8char.php');
        $migration->up();

        $game->refresh();

        $this->assertNotNull($game->share_code);
        $this->assertMatchesRegularExpression('/^[abcdefghjkmnpqrstuvwxyz23456789]{8}$/', $game->share_code);
    }
}
