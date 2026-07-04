<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\SiteManager\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebsiteManagementTest extends TestCase
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

    public function test_admin_can_create_website(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/sites', [
                'domain_url' => 'https://testsite.com',
                'api_key' => 'wp-api-token-12345',
                'is_active' => true,
                'is_default' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.domain_url', 'https://testsite.com')
            ->assertJsonPath('data.is_default', true)
            ->assertJsonMissing(['api_key']); // verify api_key is hidden in serialization

        $site = Site::where('domain_url', 'https://testsite.com')->firstOrFail();
        $this->assertEquals('wp-api-token-12345', $site->api_key); // Eloquent automatically decrypts via casting
        $this->assertNotEquals('wp-api-token-12345', DB::table('sites')->where('domain_url', 'https://testsite.com')->first()->api_key); // raw DB contains encrypted text
    }

    public function test_cannot_set_inactive_site_as_default(): void
    {
        $site = Site::create([
            'domain_url' => 'https://inactivesite.com',
            'api_key' => 'secret',
            'is_active' => false,
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/sites/{$site->id}/set-default");

        $response->assertStatus(500); // Throws exception
        $this->assertFalse($site->fresh()->is_default);
    }

    public function test_connection_validation_endpoint(): void
    {
        // Mock custom WordPress plugin endpoint
        Http::fake([
            'https://connectedwp.com/wp-json/ai-news/v1/ping' => Http::response(['version' => '1.2.3'], 200),
        ]);

        $site = Site::create([
            'domain_url' => 'https://connectedwp.com',
            'api_key' => 'token',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/sites/{$site->id}/validate");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'connected')
            ->assertJsonPath('plugin_version', '1.2.3');

        $site->refresh();
        $this->assertEquals('connected', $site->status);
        $this->assertEquals('1.2.3', $site->plugin_version);
    }
}
