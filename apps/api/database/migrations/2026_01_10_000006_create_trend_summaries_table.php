<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only history table — no unique on user_id
        // Dashboard queries: TrendSummary::where('user_id', $id)->latest('computed_at')->first()
        Schema::create('trend_summaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('computed_at');
            $table->integer('games_analysed');
            $table->decimal('avg_accuracy', 5, 2);
            $table->decimal('blunders_per_game', 5, 2);
            $table->foreignUuid('top_mistake_tag_id')->nullable()->constrained('mistake_tags')->nullOnDelete();
            $table->string('opening_weakness')->nullable();
            $table->string('phase_weakness')->nullable();
            $table->json('summary_json');

            $table->index('user_id');
            $table->index('computed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trend_summaries');
    }
};
