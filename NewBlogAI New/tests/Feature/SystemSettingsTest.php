<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\SystemSettings\Models\Setting;
use App\Modules\SystemSettings\Services\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $support;

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

        $this->support = User::create([
            'name'     => 'Support User',
            'email'    => 'support@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->support->role = 3; // Support
        $this->support->save();

        Cache::flush();
    }

    public function test_admin_can_retrieve_settings(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/settings');

        $response->assertStatus(200)
            ->assertJsonPath('settings.currency', 'USD')
            ->assertJsonPath('settings.timezone', 'UTC');
    }

    public function test_admin_can_update_settings(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/settings', [
                'currency'            => 'INR',
                'timezone'            => 'Asia/Kolkata',
                'language'            => 'hi',
                'ai_default_provider' => 'gemini',
                'ai_default_model'    => 'gemini-2.0-flash',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('settings.currency', 'INR')
            ->assertJsonPath('settings.timezone', 'Asia/Kolkata')
            ->assertJsonPath('settings.language', 'hi');

        $this->assertDatabaseHas('settings', [
            'key' => 'currency',
            'value' => json_encode('INR')
        ]);
    }

    public function test_support_cannot_update_settings(): void
    {
        $response = $this->actingAs($this->support)
            ->postJson('/api/v1/settings', [
                'currency' => 'INR',
            ]);

        $response->assertStatus(403);
    }

    public function test_settings_are_cached_centrally(): void
    {
        $service = new SystemSettingsService();

        // Fresh retrieval triggers DB hit
        $val1 = $service->get('currency', 'USD');
        $this->assertEquals('USD', $val1);

        // Put direct config in DB (bypassing service to test cache)
        Setting::updateOrCreate(['key' => 'currency'], ['value' => 'INR']);

        // Service should still return cached 'USD'
        $val2 = $service->get('currency', 'USD');
        $this->assertEquals('USD', $val2);

        // Service sets new value, cache is cleared
        $service->set('currency', 'INR');
        $this->assertEquals('INR', $service->get('currency'));
    }
}
