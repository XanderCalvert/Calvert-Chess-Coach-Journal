<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\ManualNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NullableFkTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_game_sets_manual_note_game_id_to_null(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create();
        $note = ManualNote::factory()->create([
            'user_id' => $user->id,
            'game_id' => $game->id,
        ]);

        $game->delete();

        $this->assertDatabaseHas('manual_notes', ['id' => $note->id]);
        $this->assertNull($note->fresh()->game_id);
    }

    public function test_manual_note_survives_after_game_deletion(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create();
        $note = ManualNote::factory()->create([
            'user_id'   => $user->id,
            'game_id'   => $game->id,
            'note_text' => 'Keep this note',
        ]);

        $game->delete();

        $fresh = $note->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('Keep this note', $fresh->note_text);
        $this->assertSame($user->id, $fresh->user_id);
    }
}
