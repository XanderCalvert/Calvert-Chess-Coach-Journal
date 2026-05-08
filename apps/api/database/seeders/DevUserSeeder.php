<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DevUserSeeder extends Seeder
{
    public const UUID = '00000000-0000-0000-0000-000000000001';

    public function run(): void
    {
        if (User::find(self::UUID)) {
            return;
        }

        $user = new User();
        $user->id = self::UUID;
        $user->email = 'dev@local';
        $user->password = 'password'; // cast to hashed by model
        $user->display_name = 'Dev User';
        $user->save();
    }
}
