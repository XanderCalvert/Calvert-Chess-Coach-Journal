<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notes are append-only — only created_at, no updated_at
        // Both game_id and key_moment_id are nullable; app layer enforces at least one is set
        Schema::create('manual_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->foreignUuid('key_moment_id')->nullable()->constrained('key_moments')->nullOnDelete();
            $table->text('note_text');
            $table->string('coach_agreement')->default('not_set');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_notes');
    }
};
