<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(MistakeTagSeeder::class);

        if (! App::isProduction()) {
            User::factory()->create([
                'display_name' => 'Test User',
                'email'        => 'test@example.com',
            ]);
        }
    }
}
