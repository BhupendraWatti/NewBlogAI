<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\PromptManager\Models\Prompt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->user->role = 2; // Admin
        $this->user->save();
    }

    public function test_user_can_create_prompt(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/prompts', [
                'name' => 'SEO Meta generator',
                'prompt' => 'Write meta tags for @{{topic}}.',
                'category' => 'SEO',
                'variables' => ['topic'],
                'version' => 'v1.1',
                'status' => 'active',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'SEO Meta generator')
            ->assertJsonPath('data.category', 'SEO')
            ->assertJsonPath('data.variables', ['topic'])
            ->assertJsonPath('data.version', 'v1.1');

        $this->assertDatabaseHas('prompts', [
            'name' => 'SEO Meta generator',
            'category' => 'SEO',
        ]);
    }

    public function test_user_can_list_and_filter_prompts(): void
    {
        // Seed some prompts
        Prompt::create([
            'name' => 'A Prompt',
            'prompt' => 'Content A',
            'category' => 'Tech',
            'variables' => [],
            'version' => 'v1.0',
            'status' => 'active',
        ]);

        Prompt::create([
            'name' => 'B Prompt',
            'prompt' => 'Content B',
            'category' => 'Finance',
            'variables' => [],
            'version' => 'v1.0',
            'status' => 'inactive',
        ]);

        // Filter by category = Tech
        $response1 = $this->actingAs($this->user)
            ->getJson('/api/v1/prompts?category=Tech');

        $response1->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'A Prompt');

        // Filter by status = inactive
        $response2 = $this->actingAs($this->user)
            ->getJson('/api/v1/prompts?status=inactive');

        $response2->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'B Prompt');

        // Search
        $response3 = $this->actingAs($this->user)
            ->getJson('/api/v1/prompts?search=Content B');

        $response3->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'B Prompt');
    }
}
