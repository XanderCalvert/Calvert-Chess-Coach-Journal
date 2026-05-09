<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SessionsUuidTest extends TestCase
{
    use RefreshDatabase;

    public function test_uuid_stores_in_sessions_user_id_without_truncation(): void
    {
        $user = User::factory()->create();

        DB::table('sessions')->insert([
            'id'            => 'test-session-id-' . uniqid(),
            'user_id'       => $user->id,
            'ip_address'    => '127.0.0.1',
            'user_agent'    => 'PHPUnit',
            'payload'       => base64_encode('{}'),
            'last_activity' => time(),
        ]);

        $stored = DB::table('sessions')->where('user_id', $user->id)->first();

        $this->assertNotNull($stored);
        $this->assertSame($user->id, $stored->user_id);
    }

    public function test_sessions_user_id_is_nullable(): void
    {
        DB::table('sessions')->insert([
            'id'            => 'guest-session-' . uniqid(),
            'user_id'       => null,
            'ip_address'    => '127.0.0.1',
            'user_agent'    => 'PHPUnit',
            'payload'       => base64_encode('{}'),
            'last_activity' => time(),
        ]);

        $stored = DB::table('sessions')->where('user_id', null)->first();
        $this->assertNotNull($stored);
        $this->assertNull($stored->user_id);
    }
}
