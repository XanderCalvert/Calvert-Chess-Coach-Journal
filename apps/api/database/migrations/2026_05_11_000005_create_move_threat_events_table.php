<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('move_threat_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignUuid('threat_source_move_id')->nullable()->constrained('moves')->nullOnDelete();
            $table->foreignUuid('response_move_id')->nullable()->constrained('moves')->nullOnDelete();
            $table->foreignUuid('resolved_by_move_id')->nullable()->constrained('moves')->nullOnDelete();
            $table->string('threat_type', 64);
            $table->string('threat_level', 16);
            $table->string('response_status', 32);
            $table->string('confidence', 16);
            $table->json('evidence_json')->nullable();
            $table->string('detector_version', 16)->default('1.0');
            $table->timestamps();

            $table->index('game_id');
            $table->index('threat_source_move_id');
            $table->index(['game_id', 'response_status']);
            $table->unique(['game_id', 'response_move_id', 'threat_type', 'detector_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('move_threat_events');
    }
};
