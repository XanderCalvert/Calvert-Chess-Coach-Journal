<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moves', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained()->cascadeOnDelete();
            $table->integer('move_number');
            $table->string('san');
            $table->string('uci');
            $table->string('fen_before');
            $table->string('fen_after');
            $table->string('colour');
            // cp_score, cp_loss, classification are null until engine analysis runs
            $table->integer('cp_score')->nullable();
            $table->integer('cp_loss')->nullable();
            $table->string('classification')->nullable();

            $table->unique(['game_id', 'move_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moves');
    }
};
