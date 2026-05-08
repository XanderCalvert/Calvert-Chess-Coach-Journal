<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->foreignUuid('connected_account_id')->nullable()->after('user_id')
                ->constrained('connected_accounts')->nullOnDelete();
            $table->string('platform')->nullable()->after('connected_account_id');
            $table->string('time_control')->nullable()->after('platform');
            $table->boolean('rated')->nullable()->after('time_control');
            $table->smallInteger('user_rating_before')->nullable()->after('rated');
            $table->smallInteger('user_rating_after')->nullable()->after('user_rating_before');
            $table->string('opponent_username')->nullable()->after('user_rating_after');
            $table->smallInteger('opponent_rating')->nullable()->after('opponent_username');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropForeign(['connected_account_id']);
            $table->dropColumn([
                'connected_account_id',
                'platform',
                'time_control',
                'rated',
                'user_rating_before',
                'user_rating_after',
                'opponent_username',
                'opponent_rating',
            ]);
        });
    }
};
