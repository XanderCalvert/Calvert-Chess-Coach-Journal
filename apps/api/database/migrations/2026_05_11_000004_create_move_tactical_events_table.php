<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('move_tactical_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('move_id')->constrained('moves')->cascadeOnDelete();
            $table->string('motif', 64);
            $table->string('severity', 16);
            $table->string('confidence', 16);
            $table->string('attacker_square', 4)->nullable();
            $table->string('target_square', 4)->nullable();
            $table->unsignedTinyInteger('defender_count')->nullable();
            $table->unsignedTinyInteger('attacker_count')->nullable();
            $table->json('evidence_json')->nullable();
            $table->string('detector_version', 16)->default('1.0');
            $table->timestamps();

            $table->index('move_id');
            $table->index('motif');
            $table->unique(['move_id', 'motif', 'detector_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('move_tactical_events');
    }
};
