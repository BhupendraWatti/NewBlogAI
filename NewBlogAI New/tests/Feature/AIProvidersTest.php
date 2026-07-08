<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\AIProviderManager\Models\AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AIProvidersTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->role = 2; // Admin
        $this->admin->save();
    }

    public function test_admin_can_create_provider(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/providers', [
                'provider_key' => 'openai',
                'name' => 'OpenAI Main',
                'api_key' => 'sk-test123456789',
                'default_model' => 'gpt-4o',
                'is_default' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.provider_key', 'openai')
            ->assertJsonPath('data.is_default', true)
            ->assertJsonMissing(['api_key']); // verify api_key is hidden in API Resource response

        // Verify key is encrypted in database
        $dbRecord = AIProvider::where('provider_key', 'openai')->firstOrFail();
        $this->assertEquals('sk-test123456789', $dbRecord->api_key); // Eloquent automatically decrypts via casting
        $this->assertNotEquals('sk-test123456789', DB::table('ai_providers')->where('provider_key', 'openai')->first()->api_key); // raw DB contains encrypted text
    }

    public function test_admin_can_update_provider(): void
    {
        $provider = AIProvider::create([
            'provider_key' => 'gemini',
            'name' => 'Google Gemini',
            'api_key' => 'initial-key',
            'default_model' => 'gemini-2.5-flash',
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/providers/{$provider->id}", [
                'name' => 'Updated Google Gemini',
                'api_key' => 'new-secret-key',
                'default_model' => 'gemini-2.5-pro',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Google Gemini')
            ->assertJsonPath('data.default_model', 'gemini-2.5-pro');

        $provider->refresh();
        $this->assertEquals('new-secret-key', $provider->api_key);
    }

    public function test_cannot_set_disabled_provider_as_default(): void
    {
        $provider = AIProvider::create([
            'provider_key' => 'claude',
            'name' => 'Claude',
            'api_key' => 'secret',
            'is_enabled' => false,
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/providers/{$provider->id}/set-default");

        $response->assertStatus(500); // throws validation/runtime exception
        $this->assertFalse($provider->fresh()->is_default);
    }

    public function test_test_connection_endpoint(): void
    {
        // Mock OpenAI API endpoint response
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response(['choices' => []], 200),
        ]);

        $provider = AIProvider::create([
            'provider_key' => 'openai',
            'name' => 'OpenAI',
            'api_key' => 'valid-key',
            'default_model' => 'gpt-3.5-turbo',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/providers/{$provider->id}/test-connection");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Connection test successful!');
    }

    public function test_ai_provider_driver_timeouts_are_ninety_seconds(): void
    {
        $drivers = [
            'OpenAIDriver.php',
            'GoogleGeminiDriver.php',
            'ClaudeDriver.php',
            'GroqDriver.php',
            'OllamaDriver.php',
            'OpenRouterDriver.php',
        ];

        foreach ($drivers as $driver) {
            $path = app_path("Modules/AIProviderManager/Drivers/{$driver}");
            $this->assertFileExists($path);
            $content = file_get_contents($path);
            $this->assertStringContainsString('timeout', $content);
            $this->assertStringContainsString('90', $content);
        }
    }
}
