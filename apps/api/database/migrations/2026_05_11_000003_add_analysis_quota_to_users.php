<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('analysis_quota_used')->default(0)->after('subscription_tier');
            // Default to current date; the model resets to start-of-month on first quota check
            $table->date('quota_period_start')->default(DB::raw('CURRENT_DATE'))->after('analysis_quota_used');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['analysis_quota_used', 'quota_period_start']);
        });
    }
};
