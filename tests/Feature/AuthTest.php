<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonStructure(['status', 'version', 'timestamp'])
            ->assertJson(['status' => 'ok']);
    }

    public function test_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-client')->plainTextToken;

        // Verify token exists before logout
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/logout');

        $response->assertOk()
            ->assertJson(['message' => 'Logged out successfully']);

        // Token should be deleted from database
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unauthenticated_access_returns_401(): void
    {
        $response = $this->getJson('/api/v1/user');

        $response->assertUnauthorized();
    }
}
