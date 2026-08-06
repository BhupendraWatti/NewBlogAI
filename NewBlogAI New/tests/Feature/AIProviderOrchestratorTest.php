<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\ContentGeneration\Services\ContentGenerationService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AIProviderOrchestratorTest extends TestCase
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

    /**
     * Test drop of unique constraint on provider_key (allows multiple credentials).
     */
    public function test_can_configure_multiple_credentials_for_same_provider_key(): void
    {
        $geminiFree = AIProvider::create([
            'provider_key' => 'gemini',
            'name' => 'Gemini Free',
            'api_key' => 'free-key-123',
            'default_model' => 'gemini-2.5-flash',
            'tier' => 'free',
            'priority' => 1,
            'status' => 'healthy',
            'is_enabled' => true,
        ]);

        $geminiPaid = AIProvider::create([
            'provider_key' => 'gemini',
            'name' => 'Gemini Paid',
            'api_key' => 'paid-key-456',
            'default_model' => 'gemini-2.5-pro',
            'tier' => 'paid',
            'priority' => 10,
            'status' => 'healthy',
            'is_enabled' => true,
        ]);

        $this->assertDatabaseCount('ai_providers', 2);
        $this->assertEquals('free-key-123', $geminiFree->api_key);
        $this->assertEquals('paid-key-456', $geminiPaid->api_key);
    }

    /**
     * Test priority sorting in getAvailableProviders().
     */
    public function test_providers_are_sorted_by_priority_ascending(): void
    {
        // 1. Seed providers with different priorities
        AIProvider::create([
            'provider_key' => 'openai',
            'name' => 'OpenAI Paid',
            'api_key' => 'key-1',
            'priority' => 20,
            'status' => 'healthy',
            'is_enabled' => true,
        ]);

        AIProvider::create([
            'provider_key' => 'gemini',
            'name' => 'Gemini Free',
            'api_key' => 'key-2',
            'priority' => 5,
            'status' => 'healthy',
            'is_enabled' => true,
        ]);

        AIProvider::create([
            'provider_key' => 'groq',
            'name' => 'Groq Free',
            'api_key' => 'key-3',
            'priority' => 1,
            'status' => 'healthy',
            'is_enabled' => true,
        ]);

        $service = app(ContentGenerationService::class);
        $available = $service->getAvailableProviders();

        $this->assertCount(3, $available);
        $this->assertEquals('Groq Free', $available[0]->name);   // Priority 1
        $this->assertEquals('Gemini Free', $available[1]->name); // Priority 5
        $this->assertEquals('OpenAI Paid', $available[2]->name);  // Priority 20
    }

    /**
     * Test that permanent errors (HTTP 401) disable the provider key immediately.
     */
    public function test_permanent_errors_disable_provider_immediately(): void
    {
        $provider = AIProvider::create([
            'provider_key' => 'openai',
            'name' => 'OpenAI Bad Key',
            'api_key' => 'sk-invalid',
            'priority' => 1,
            'status' => 'healthy',
            'is_enabled' => true,
        ]);

        // Throw an exception representing a 401 Unauthorized
        $provider->handleFailure(new Exception('Status 401: Unauthorized API Key'));

        $provider->refresh();
        $this->assertEquals('disabled', $provider->status);
        $this->assertFalse($provider->is_enabled);
        $this->assertNull($provider->cooldown_until);
    }

    /**
     * Test that rate limit errors (HTTP 429) trigger a cooldown state.
     */
    public function test_rate_limit_errors_trigger_cooldown(): void
    {
        $provider = AIProvider::create([
            'provider_key' => 'gemini',
            'name' => 'Gemini Cooldown',
            'api_key' => 'key-temp',
            'priority' => 1,
            'status' => 'healthy',
            'is_enabled' => true,
        ]);

        // Throw rate limit exception
        $provider->handleFailure(new Exception('Status 429: Please retry in 60s'));

        $provider->refresh();
        $this->assertEquals('cooldown', $provider->status);
        $this->assertTrue($provider->is_enabled); // remains enabled for future try
        $this->assertNotNull($provider->cooldown_until);
        $this->assertTrue($provider->cooldown_until->isFuture());
    }

    public function test_gemini_rate_limit_without_reset_hint_uses_short_cooldown(): void
    {
        $provider = AIProvider::create([
            'provider_key' => 'gemini',
            'name' => 'Gemini Free Cooldown',
            'api_key' => 'key-temp',
            'priority' => 1,
            'status' => 'healthy',
            'is_enabled' => true,
        ]);

        $provider->handleFailure(new Exception('Gemini API error: Status 429 - RESOURCE_EXHAUSTED'));

        $provider->refresh();
        $this->assertEquals('cooldown', $provider->status);
        $this->assertTrue($provider->cooldown_until->lessThanOrEqualTo(now()->addSeconds(120)));
    }

    /**
     * Test that dynamic cooldown recovery restores provider to healthy.
     */
    public function test_cooldown_recovers_when_cooldown_until_passes(): void
    {
        $provider = AIProvider::create([
            'provider_key' => 'gemini',
            'name' => 'Gemini Recoverable',
            'api_key' => 'key-rec',
            'priority' => 1,
            'status' => 'cooldown',
            'cooldown_until' => now()->subMinutes(5), // in the past
            'is_enabled' => true,
        ]);

        $service = app(ContentGenerationService::class);
        $available = $service->getAvailableProviders();

        // The query / checkout check should run checkRecovery() and return it
        $this->assertCount(1, $available);
        $this->assertEquals('healthy', $available->first()->status);
        $this->assertNull($available->first()->cooldown_until);
    }

    /**
     * Test handleSuccess resets health statistics and counts.
     */
    public function test_handle_success_increments_and_resets_health(): void
    {
        $provider = AIProvider::create([
            'provider_key' => 'gemini',
            'name' => 'Gemini Test Success',
            'api_key' => 'key-success',
            'priority' => 1,
            'status' => 'cooldown',
            'cooldown_until' => now()->addHour(),
            'is_enabled' => true,
            'success_count' => 5,
        ]);

        $provider->handleSuccess();

        $provider->refresh();
        $this->assertEquals('healthy', $provider->status);
        $this->assertNull($provider->cooldown_until);
        $this->assertEquals(6, $provider->success_count);
        $this->assertNotNull($provider->last_used);
    }
}
