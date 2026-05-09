<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moves', function (Blueprint $table) {
            // Deterministic coaching layer (Sprint 2)
            $table->json('themes')->nullable()->after('classification');
            $table->json('tactical_flags')->nullable()->after('themes');
            $table->json('threat_awareness')->nullable()->after('tactical_flags');
            $table->text('risk_note')->nullable()->after('threat_awareness');
            $table->unsignedSmallInteger('consecutive_miss_count')->nullable()->after('risk_note');
            $table->unsignedSmallInteger('coaching_version')->nullable()->after('consecutive_miss_count');

            // Position context
            $table->enum('game_phase', ['opening', 'middlegame', 'endgame'])->nullable()->after('coaching_version');
            $table->unsignedSmallInteger('complexity_score')->nullable()->after('game_phase');

            // AI explanation slot (Sprint 5) — stored so it is never regenerated unnecessarily
            $table->text('ai_explanation')->nullable()->after('complexity_score');
            $table->enum('ai_explanation_status', ['pending', 'complete', 'failed'])->nullable()->after('ai_explanation');
            $table->string('ai_explanation_model', 64)->nullable()->after('ai_explanation_status');
        });
    }

    public function down(): void
    {
        Schema::table('moves', function (Blueprint $table) {
            $table->dropColumn([
                'themes', 'tactical_flags', 'threat_awareness', 'risk_note', 'consecutive_miss_count', 'coaching_version',
                'game_phase', 'complexity_score',
                'ai_explanation', 'ai_explanation_status', 'ai_explanation_model',
            ]);
        });
    }
};
