<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('key_moments', function (Blueprint $table) {
            $table->foreignUuid('mistake_tag_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('key_moments', function (Blueprint $table) {
            $table->foreignUuid('mistake_tag_id')->nullable(false)->change();
        });
    }
};
