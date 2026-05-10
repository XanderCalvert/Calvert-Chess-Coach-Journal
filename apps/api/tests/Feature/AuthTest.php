<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['password' => 'secret123'], $attrs));
    }

    public function test_login_with_valid_credentials_returns_token(): void
    {
        $this->createUser(['email' => 'test@example.com']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['token', 'user' => ['id', 'email']]);
    }

    public function test_login_with_invalid_credentials_returns_401(): void
    {
        $this->createUser(['email' => 'test@example.com']); // user must exist for auth to attempt it

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    public function test_me_without_token_returns_401(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_me_with_valid_token_returns_user(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJson(['id' => $user->id, 'email' => $user->email]);
    }

    public function test_logout_revokes_token(): void
    {
        $user = $this->createUser(['email' => 'test@example.com']);

        // Login creates a real PAT in the database.
        $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@example.com',
            'password' => 'secret123',
        ])->assertStatus(201);

        $this->assertSame(1, $user->tokens()->count());

        // Logout deletes it.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(204);

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_second_login_replaces_previous_token(): void
    {
        $user = $this->createUser(['email' => 'test@example.com']);

        $credentials = ['email' => 'test@example.com', 'password' => 'secret123'];

        $this->postJson('/api/v1/auth/login', $credentials)->assertStatus(201);
        $this->postJson('/api/v1/auth/login', $credentials)->assertStatus(201);

        $this->assertSame(1, $user->tokens()->where('name', 'web-session')->count());
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        $this->createUser(['email' => 'test@example.com']);

        $payload = ['email' => 'test@example.com', 'password' => 'wrong'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', $payload);
        }

        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(429);
    }

    public function test_register_with_valid_data_creates_user_and_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'display_name'          => 'Alice',
            'email'                 => 'alice@example.com',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'has_connected_accounts']]);

        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
    }

    public function test_register_with_duplicate_email_returns_422(): void
    {
        $this->createUser(['email' => 'alice@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'display_name'          => 'Alice',
            'email'                 => 'alice@example.com',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertStatus(422);
    }

    public function test_register_without_password_confirmation_returns_422(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'display_name' => 'Alice',
            'email'        => 'alice@example.com',
            'password'     => 'secret123',
        ])->assertStatus(422);
    }

    public function test_register_is_throttled_after_five_attempts(): void
    {
        $payload = [
            'display_name'          => 'Spammer',
            'email'                 => 'spam@example.com',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/register', $payload);
            // Vary email to avoid unique constraint after first success
            $payload['email'] = "spam{$i}@example.com";
        }

        $this->postJson('/api/v1/auth/register', array_merge($payload, ['email' => 'final@example.com']))
            ->assertStatus(429);
    }

    public function test_fresh_registered_user_has_connected_accounts_false(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'display_name'          => 'Bob',
            'email'                 => 'bob@example.com',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertStatus(201)
            ->assertJson(['user' => ['has_connected_accounts' => false]]);
    }

    public function test_me_returns_has_connected_accounts(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonStructure(['has_connected_accounts']);
    }
}
