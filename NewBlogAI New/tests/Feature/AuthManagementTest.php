<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);
        $this->user->role = 2; // Admin
        $this->user->save();
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.email', 'test@example.com');

        $this->assertAuthenticatedAs($this->user);

        // Verify activity logged
        $this->assertDatabaseHas('auth_activities', [
            'user_id' => $this->user->id,
            'event_type' => 'login',
        ]);
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
        $this->assertGuest();

        // Verify failed attempt logged
        $this->assertDatabaseHas('auth_activities', [
            'user_id' => null,
            'event_type' => 'login_failed',
        ]);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'test@example.com');
    }

    public function test_authenticated_user_can_update_profile(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/auth/profile', [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.name', 'Updated Name')
            ->assertJsonPath('user.email', 'updated@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);

        $this->assertDatabaseHas('auth_activities', [
            'user_id' => $this->user->id,
            'event_type' => 'profile_updated',
        ]);
    }

    public function test_authenticated_user_can_change_password(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'password123',
                'new_password' => 'newsecurepassword',
                'new_password_confirmation' => 'newsecurepassword',
            ]);

        $response->assertStatus(200);

        // Verify password hash updated
        $this->user->refresh();
        $this->assertTrue(Hash::check('newsecurepassword', $this->user->password));

        $this->assertDatabaseHas('auth_activities', [
            'user_id' => $this->user->id,
            'event_type' => 'password_changed',
        ]);
    }

    public function test_user_can_logout(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200);
        $this->assertGuest();

        $this->assertDatabaseHas('auth_activities', [
            'user_id' => $this->user->id,
            'event_type' => 'logout',
        ]);
    }
}
