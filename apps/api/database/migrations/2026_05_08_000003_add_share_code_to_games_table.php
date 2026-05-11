<?php

use App\Support\ShareCodeGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('share_code', 6)->nullable()->unique()->after('external_id');
        });

        // Backfill existing games. Uses DB::table() — safe regardless of future model changes.
        DB::table('games')->whereNull('share_code')->orderBy('created_at')->each(function ($game) {
            DB::table('games')
                ->where('id', $game->id)
                ->update(['share_code' => ShareCodeGenerator::generate()]);
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('share_code');
        });
    }
};
