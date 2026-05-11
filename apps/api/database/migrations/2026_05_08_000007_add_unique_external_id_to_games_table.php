<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Partial unique: only enforced when both fields are non-null (synced games)
            $table->unique(['connected_account_id', 'external_id'], 'games_connected_account_external_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropUnique('games_connected_account_external_id_unique');
        });
    }
};
