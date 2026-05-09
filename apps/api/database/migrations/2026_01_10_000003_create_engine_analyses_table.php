<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engine_analyses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('move_id')->constrained('moves')->cascadeOnDelete();
            $table->string('best_move_uci');
            $table->string('best_move_san');
            $table->json('best_line');
            $table->integer('depth');
            $table->integer('cp_evaluation');
            $table->timestamp('analysed_at');

            // One analysis per move
            $table->unique('move_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engine_analyses');
    }
};
