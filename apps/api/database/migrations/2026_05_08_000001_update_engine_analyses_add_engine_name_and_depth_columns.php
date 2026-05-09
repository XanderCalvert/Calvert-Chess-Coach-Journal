<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_analyses', function (Blueprint $table) {
            // Drop the single-column unique so we can replace it with a composite
            $table->dropUnique(['move_id']);

            $table->string('engine_name')->default('stockfish')->after('move_id');
            $table->integer('depth_requested')->after('depth');
            $table->integer('depth_reached')->after('depth_requested');

            // One analysis per (move, engine) — future-proof for multiple engines
            $table->unique(['move_id', 'engine_name']);
        });
    }

    public function down(): void
    {
        Schema::table('engine_analyses', function (Blueprint $table) {
            $table->dropUnique(['move_id', 'engine_name']);
            $table->dropColumn(['engine_name', 'depth_requested', 'depth_reached']);
            $table->unique('move_id');
        });
    }
};
