<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('key_moments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('move_id')->constrained('moves')->cascadeOnDelete();
            $table->foreignUuid('mistake_tag_id')->constrained('mistake_tags');
            $table->integer('rank');
            $table->integer('cp_loss');
            $table->text('explanation_text')->nullable();
            $table->string('explanation_status')->default('pending');
            $table->string('game_phase');
            // Deviation: updated_at added; explanation_status transitions warrant it
            $table->timestamps();

            $table->unique(['game_id', 'rank']);
        });

        // Rank guard: only valid on Postgres; SQLite ignores CHECK constraints
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE key_moments ADD CONSTRAINT key_moments_rank_check CHECK (rank BETWEEN 1 AND 3)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('key_moments');
    }
};
