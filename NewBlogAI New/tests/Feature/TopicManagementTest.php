<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\TopicManager\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopicManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Prompt $prompt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'     => 'Admin User',
            'email'    => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->role = 2; // Admin
        $this->admin->save();

        $this->prompt = Prompt::create([
            'name'     => 'Test Prompt',
            'promt'    => 'Generate text.',
            'category' => 'Testing',
            'status'   => 'active',
        ]);
    }

    public function test_admin_can_create_topic_with_prompt(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/topics', [
                'name'                 => 'Quantum Physics',
                'category'             => 'Science',
                'priority'             => 'high',
                'language'             => 'en',
                'status'               => 'active',
                'generation_frequency' => 'daily',
                'tags'                 => ['physics', 'quantum'],
                'prompt_id'            => $this->prompt->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Quantum Physics')
            ->assertJsonPath('data.prompt_id', $this->prompt->id);

        $this->assertDatabaseHas('topics', [
            'name'      => 'Quantum Physics',
            'prompt_id' => $this->prompt->id,
        ]);
    }

    public function test_duplicate_prevention_same_category(): void
    {
        // First creation
        Topic::create([
            'name'                 => 'SEO Writing',
            'category'             => 'Marketing',
            'priority'             => 'medium',
            'language'             => 'en',
            'status'               => 'active',
            'generation_frequency' => 'weekly',
        ]);

        // Attempting to create duplicate
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/topics', [
                'name'                 => 'SEO Writing',
                'category'             => 'Marketing',
                'priority'             => 'medium',
                'language'             => 'en',
                'status'               => 'active',
                'generation_frequency' => 'weekly',
            ]);

        $response->assertStatus(500); // Throws runtime/duplicate exception
    }

    public function test_soft_delete_and_restore_lifecycle(): void
    {
        $topic = Topic::create([
            'name'                 => 'Deleteme',
            'category'             => 'Trash',
            'priority'             => 'low',
            'language'             => 'en',
            'status'               => 'draft',
            'generation_frequency' => 'monthly',
        ]);

        // Delete
        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/topics/{$topic->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('topics', ['id' => $topic->id]);

        // Restore
        $restoreResponse = $this->actingAs($this->admin)
            ->postJson("/api/v1/topics/{$topic->id}/restore");

        $restoreResponse->assertStatus(200);
        $this->assertDatabaseHas('topics', [
            'id'         => $topic->id,
            'deleted_at' => null
        ]);
    }
}
