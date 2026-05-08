<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DevUserSeeder extends Seeder
{
    public const UUID = '00000000-0000-0000-0000-000000000001';

    public function run(): void
    {
        User::updateOrCreate(
            ['id' => self::UUID],
            [
                'email' => 'dev@local',
                'password' => 'password', // cast to hashed by model
                'display_name' => 'Dev User',
            ]
        );
    }
}
