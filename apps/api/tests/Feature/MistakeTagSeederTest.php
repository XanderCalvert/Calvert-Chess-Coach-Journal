<?php

namespace Tests\Feature;

use Database\Seeders\MistakeTagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MistakeTagSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_inserts_all_tags_on_first_run(): void
    {
        $seeder = new MistakeTagSeeder();
        $seeder->run();

        $countAfterFirst = DB::table('mistake_tags')->count();
        $this->assertSame(12, $countAfterFirst);
    }

    public function test_seeder_is_idempotent(): void
    {
        $seeder = new MistakeTagSeeder();
        $seeder->run();
        $countAfterFirst = DB::table('mistake_tags')->count();

        $seeder->run();

        $this->assertSame($countAfterFirst, DB::table('mistake_tags')->count());
    }

    public function test_seeder_preserves_all_slugs(): void
    {
        (new MistakeTagSeeder())->run();

        $expectedSlugs = [
            'hanging-piece', 'missed-tactic', 'missed-capture', 'king-safety',
            'poor-development', 'bad-trade', 'pawn-weakness', 'opening-principle',
            'endgame-technique', 'overlooked-threat', 'time-pressure', 'positional-mistake',
        ];

        foreach ($expectedSlugs as $slug) {
            $this->assertDatabaseHas('mistake_tags', ['slug' => $slug]);
        }
    }
}
