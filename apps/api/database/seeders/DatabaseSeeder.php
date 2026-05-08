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
            $this->call(DevUserSeeder::class);

            User::updateOrCreate(
                ['email' => 'test@example.com'],
                [
                    'display_name' => 'Test User',
                    'password' => 'password',
                ]
            );

            $localOnlySeeder = 'Database\\Seeders\\LocalOnlySeeder';
            if (class_exists($localOnlySeeder)) {
                $this->call($localOnlySeeder);
            }
        }
    }
}
