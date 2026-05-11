<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weakness_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('connected_account_id')->constrained()->cascadeOnDelete();
            $table->timestamp('computed_at');

            // Scalar metadata
            $table->string('profile_version', 16)->default('1.0');
            $table->unsignedSmallInteger('window_size');
            $table->unsignedSmallInteger('analysed_games_count');
            $table->foreignUuid('computed_from_game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->foreignUuid('computed_to_game_id')->nullable()->constrained('games')->nullOnDelete();

            // Scalar weakness indicators (null when insufficient data)
            $table->string('weakest_phase', 16)->nullable();
            $table->string('top_motif', 64)->nullable();
            $table->foreignUuid('top_mistake_tag_id')->nullable()->constrained('mistake_tags')->nullOnDelete();
            $table->decimal('threat_response_rate', 5, 2)->nullable();

            // Breakdown JSON columns
            $table->json('phase_breakdown');
            $table->json('opening_breakdown');
            $table->json('motif_frequencies');
            $table->json('threat_response_by_phase');

            // Structured coaching facts for AI consumption
            $table->json('summary_json');

            $table->index('connected_account_id');
            $table->index(['connected_account_id', 'computed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weakness_profiles');
    }
};
