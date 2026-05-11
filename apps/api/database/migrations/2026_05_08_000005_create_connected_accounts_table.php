<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connected_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform');
            $table->string('username');
            $table->string('normalised_username');
            $table->string('external_id')->nullable();
            $table->string('profile_url')->nullable();
            $table->smallInteger('rapid_rating')->nullable();
            $table->smallInteger('blitz_rating')->nullable();
            $table->smallInteger('bullet_rating')->nullable();
            $table->smallInteger('daily_rating')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status')->default('never_synced');
            $table->timestamps();

            $table->unique(['platform', 'normalised_username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connected_accounts');
    }
};
