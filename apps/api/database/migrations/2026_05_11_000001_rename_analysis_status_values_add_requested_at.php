<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('games')->where('analysis_status', 'running')->update(['analysis_status' => 'queued']);
        DB::table('games')->where('analysis_status', 'complete')->update(['analysis_status' => 'analysed']);

        Schema::table('games', function (Blueprint $table) {
            $table->timestamp('analysis_requested_at')->nullable()->after('analysis_status');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('analysis_requested_at');
        });

        DB::table('games')->where('analysis_status', 'queued')->update(['analysis_status' => 'running']);
        DB::table('games')->where('analysis_status', 'analysing')->update(['analysis_status' => 'running']);
        DB::table('games')->where('analysis_status', 'analysed')->update(['analysis_status' => 'complete']);
    }
};
