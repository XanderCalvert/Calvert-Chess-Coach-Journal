<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->text('pgn_raw');
            $table->string('white_player');
            $table->string('black_player');
            $table->dateTime('played_at');
            $table->string('result');
            $table->string('user_colour');
            $table->string('opening_name');
            $table->string('eco_code');
            $table->integer('move_count');
            $table->decimal('accuracy_pct', 5, 2)->nullable();
            $table->integer('blunder_count')->default(0);
            $table->integer('mistake_count')->default(0);
            $table->integer('inaccuracy_count')->default(0);
            $table->text('summary_text')->nullable();
            $table->string('analysis_status')->default('pending');
            $table->string('imported_from');
            $table->string('external_id')->nullable();
            // Deviation: updated_at added; analysis_status transitions make it operationally useful
            $table->timestamps();

            $table->index('user_id');
            $table->index('analysis_status');
        });

        // Partial unique index: prevent double-importing the same external game per user
        // Guarded to pgsql only — SQLite does not support partial indexes
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX games_user_import_external_unique
                 ON games (user_id, imported_from, external_id)
                 WHERE external_id IS NOT NULL'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
