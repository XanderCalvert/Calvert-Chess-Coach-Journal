<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_analyses', function (Blueprint $table) {
            $table->string('best_move_san')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('engine_analyses', function (Blueprint $table) {
            $table->string('best_move_san')->nullable(false)->change();
        });
    }
};
