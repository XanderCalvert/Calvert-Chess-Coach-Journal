<?php

namespace Tests\Feature;

use Database\Seeders\DevUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PositionAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private const STARTING_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevUserSeeder::class);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    private function mockStockfish(array $candidates): void
    {
        $mock = \Mockery::mock('overload:App\Services\StockfishService');
        $mock->shouldReceive('analysePosition')
            ->once()
            ->andReturn($candidates);
    }

    private function sampleCandidates(): array
    {
        return [
            ['rank' => 1, 'uci' => 'e2e4', 'cp' => 30,  'mate' => null, 'pv' => ['e2e4', 'e7e5']],
            ['rank' => 2, 'uci' => 'd2d4', 'cp' => 25,  'mate' => null, 'pv' => ['d2d4', 'd7d5']],
            ['rank' => 3, 'uci' => 'g1f3', 'cp' => 15,  'mate' => null, 'pv' => ['g1f3', 'g8f6']],
        ];
    }

    public function test_valid_fen_returns_candidate_response_schema(): void
    {
        $this->mockStockfish($this->sampleCandidates());

        $response = $this->postJson('/api/v1/positions/analyse', [
            'fen'     => self::STARTING_FEN,
            'multipv' => 3,
            'time_ms' => 500,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'fen',
            'side_to_move',
            'engine_version',
            'candidates' => [
                '*' => ['rank', 'uci', 'cp', 'mate', 'pv'],
            ],
        ]);

        $response->assertJsonFragment(['side_to_move' => 'w']);
        $this->assertCount(3, $response->json('candidates'));

        $first = $response->json('candidates.0');
        $this->assertSame(1, $first['rank']);
        $this->assertSame('e2e4', $first['uci']);
        $this->assertIsArray($first['pv']);
    }

    public function test_invalid_fen_returns_422(): void
    {
        $response = $this->postJson('/api/v1/positions/analyse', [
            'fen' => 'not-a-fen',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['fen']);
    }

    public function test_cache_hit_returns_same_shape(): void
    {
        // First call — hits Stockfish
        $this->mockStockfish($this->sampleCandidates());

        $payload = ['fen' => self::STARTING_FEN, 'multipv' => 3, 'time_ms' => 500];

        $first = $this->postJson('/api/v1/positions/analyse', $payload);
        $first->assertOk();

        // Second call — served from cache; mock must NOT be called again
        $second = $this->postJson('/api/v1/positions/analyse', $payload);
        $second->assertOk();

        $this->assertSame(
            $first->json('candidates'),
            $second->json('candidates'),
        );
    }

    public function test_out_of_range_parameters_return_422(): void
    {
        $this->postJson('/api/v1/positions/analyse', [
            'fen'     => self::STARTING_FEN,
            'multipv' => 6,
        ])->assertUnprocessable()->assertJsonValidationErrors(['multipv']);

        $this->postJson('/api/v1/positions/analyse', [
            'fen'     => self::STARTING_FEN,
            'time_ms' => 99999,
        ])->assertUnprocessable()->assertJsonValidationErrors(['time_ms']);
    }
}
