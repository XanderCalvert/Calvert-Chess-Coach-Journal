<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_recommendations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('mistake_tag_id')->constrained('mistake_tags');
            $table->text('reason_text');
            $table->text('description_text');
            $table->string('status')->default('active');
            $table->timestamp('completed_at')->nullable();
            // Deviation: updated_at added; status and completed_at both change over time
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_recommendations');
    }
};
