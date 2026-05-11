<?php

use App\Support\ShareCodeGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Pre-launch only — no down() because regenerating codes would invalidate any shared links.
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // Widen the column first; existing 6-char codes remain valid, but new codes are 8 chars.
        // Use raw SQL on Postgres to preserve the existing unique index.
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE games ALTER COLUMN share_code TYPE varchar(8)');
        } elseif ($driver !== 'sqlite') {
            Schema::table('games', function ($table) {
                $table->string('share_code', 8)->change();
            });
        }

        DB::table('games')->whereRaw('LENGTH(share_code) != 8')->orderBy('created_at')->each(function ($game) {
            DB::table('games')
                ->where('id', $game->id)
                ->update(['share_code' => ShareCodeGenerator::generate()]);
        });
    }

    public function down(): void
    {
        // Intentionally empty — see comment on up().
    }
};
