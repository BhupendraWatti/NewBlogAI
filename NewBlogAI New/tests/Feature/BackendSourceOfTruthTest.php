<?php

namespace Tests\Feature;

use App\Models\keys;
use App\Models\User;
use App\Modules\CustomerManager\Models\Customer;
use App\Modules\ScheduleManager\Services\ScheduleService;
use App\Modules\SiteManager\Models\Site;
use App\Modules\SiteManager\Services\PluginTokenService;
use App\Modules\SiteManager\Services\SiteConfigurationService;
use App\Modules\SubscriptionManager\Exceptions\EntitlementDeniedException;
use App\Modules\SubscriptionManager\Models\Plan;
use App\Modules\SubscriptionManager\Models\Subscription;
use App\Modules\SubscriptionManager\Services\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackendSourceOfTruthTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Customer $customer;

    protected Plan $plan;

    protected Subscription $subscription;

    protected Site $site;

    protected EntitlementService $entitlementService;

    protected SiteConfigurationService $configService;

    protected PluginTokenService $tokenService;

    protected ScheduleService $scheduleService;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Resolve Services
        $this->entitlementService = resolve(EntitlementService::class);
        $this->configService = resolve(SiteConfigurationService::class);
        $this->tokenService = resolve(PluginTokenService::class);
        $this->scheduleService = resolve(ScheduleService::class);

        // 2. Setup Customer & User
        $this->customer = Customer::create([
            'company_name' => 'Acme Corp',
            'owner_name' => 'Alice owner',
            'email' => 'alice@acme.com',
            'status' => 'active',
        ]);

        $this->user = User::create([
            'name' => 'Alice User',
            'email' => 'alice@acme.com',
            'password' => bcrypt('secret-pass'),
            'customer_id' => $this->customer->id,
        ]);

        // 3. Setup Active Plan
        $this->plan = Plan::create([
            'name' => 'Premium Plan',
            'monthly_price' => 49.00,
            'yearly_price' => 490.00,
            'max_wordpress_sites' => 3,
            'max_topics' => 10,
            'publishing_schedule_limit' => 5,
            'max_articles_per_day' => 10,
            'prompt_templates_allowed' => 5,
            'ai_providers_available' => ['openai', 'anthropic'],
            'api_keys_allowed' => 3,
            'storage_limit' => 2048,
            'status' => 'active',
            'minimum_publishing_frequency' => 'daily',
            'feature_flags' => ['seo_optimizer' => true],
        ]);

        // 4. Setup Active Subscription
        $this->subscription = Subscription::create([
            'customer_id' => $this->customer->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_period' => 'monthly',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'limits' => $this->plan->toArray(),
        ]);

        // 5. Setup Website
        $this->site = Site::create([
            'customer_id' => $this->customer->id,
            'domain_url' => 'https://acmeblog.com',
            'name' => 'Acme Blog',
            'api_key' => 'wp_app_password_value',
            'slot' => 'daily',
            'selected_topics' => ['Tech', 'Science'],
            'is_active' => true,
            'status' => 'connected',
            'timezone' => 'UTC',
        ]);
    }

    /**
     * Test EntitlementService subscription resolution and limit checks.
     */
    public function test_entitlement_service_resolves_active_subscription_and_limits(): void
    {
        $activeSub = $this->entitlementService->activeSubscription($this->customer);
        $this->assertEquals($this->subscription->id, $activeSub->id);

        $limits = $this->entitlementService->limits($activeSub);
        $this->assertEquals(3, $limits['max_wordpress_sites']);
        $this->assertEquals(10, $limits['max_topics']);
        $this->assertTrue($limits['feature_flags']['seo_optimizer']);

        // Check assertions do not throw exception under limits
        $this->entitlementService->assertCanRegisterSite($this->customer);
        $this->entitlementService->assertCanCreateSchedule($this->site);
    }

    /**
     * Test EntitlementService asserts limits and correctly denies access.
     */
    public function test_entitlement_service_asserts_limits_and_throws_exception(): void
    {
        // Reach site limit by creating 3 sites
        Site::create(['customer_id' => $this->customer->id, 'domain_url' => 'https://site2.com', 'name' => 'Site 2', 'api_key' => 'key', 'is_active' => true]);
        Site::create(['customer_id' => $this->customer->id, 'domain_url' => 'https://site3.com', 'name' => 'Site 3', 'api_key' => 'key', 'is_active' => true]);

        $this->expectException(EntitlementDeniedException::class);
        $this->expectExceptionMessage('max_wordpress_sites');
        $this->entitlementService->assertCanRegisterSite($this->customer);
    }

    /**
     * Test SiteConfigurationService builds authoritative configurations.
     */
    public function test_site_configuration_service_builds_authoritative_config(): void
    {
        $config = $this->configService->build($this->site);

        $this->assertEquals('1.0', $config['schema_version']);
        $this->assertEquals($this->site->id, $config['site']['id']);
        $this->assertEquals($this->site->domain_url, $config['site']['domain_url']);

        // Entitlements included
        $this->assertEquals('active', $config['subscription']['status']);
        $this->assertEquals('Premium Plan', $config['subscription']['plan']);

        // Legacy compatibility keys
        $this->assertEquals($this->site->id, $config['site_id']);
        $this->assertContains('Tech', $config['selected_topics']);
        $this->assertEquals('daily', $config['slot']);

        // Assert WP and AI credentials are excluded
        $this->assertArrayNotHasKey('api_key', $config);
        $this->assertArrayNotHasKey('wp_app_pwd', $config['site']);
    }

    /**
     * Test ScheduleService calculates next run time.
     */
    public function test_schedule_service_calculates_next_run(): void
    {
        $config = [
            'timezone' => 'UTC',
            'frequency' => 'daily',
            'time_of_day' => '10:00:00',
        ];

        $reference = now()->startOfDay()->setTime(8, 0); // 08:00
        $nextRun = $this->scheduleService->nextRunAt($config, $reference);

        // Next run should be today at 10:00 UTC
        $this->assertEquals($reference->setTime(10, 0)->toDateTimeString(), $nextRun->toDateTimeString());

        // Reference after scheduled time
        $referenceAfter = now()->startOfDay()->setTime(11, 0); // 11:00
        $nextRunAfter = $this->scheduleService->nextRunAt($config, $referenceAfter);

        // Next run should be tomorrow at 10:00 UTC
        $this->assertEquals($referenceAfter->addDay()->setTime(10, 0)->toDateTimeString(), $nextRunAfter->toDateTimeString());
    }

    /**
     * Test PluginTokenService authentication flow.
     */
    public function test_plugin_token_service_issues_authenticates_and_revokes(): void
    {
        // 1. Issue Token
        $token = $this->tokenService->issue($this->user);
        $this->assertNotEmpty($token);

        // 2. Authenticate Token
        $authenticatedUser = $this->tokenService->authenticate($token);
        $this->assertEquals($this->user->id, $authenticatedUser->id);

        // 3. Revoke Token
        $this->tokenService->revoke($this->user);
        $authenticatedUserAfterRevoke = $this->tokenService->authenticate($token);
        $this->assertNull($authenticatedUserAfterRevoke);
    }

    /**
     * Test Endpoint integrations for WP Plugin calls.
     */
    public function test_wp_plugin_endpoints_resolves_canonical_and_legacy_routes(): void
    {
        // Test login endpoint
        $loginResponse = $this->postJson('/api/plugin/login', [
            'email' => 'alice@acme.com',
            'password' => 'secret-pass',
        ]);
        $loginResponse->assertStatus(200)
            ->assertJsonStructure(['access_token', 'token_type']);

        $token = $loginResponse->json('access_token');

        // Test GET configuration (legacy route)
        $configResponseLegacy = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/plugin/configuration?site_url=https://acmeblog.com');
        $configResponseLegacy->assertStatus(200)
            ->assertJsonPath('site.domain_url', 'https://acmeblog.com');

        // Test GET configuration (canonical route)
        $configResponseCanonical = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/plugin/configuration?site_url=https://acmeblog.com');
        $configResponseCanonical->assertStatus(200)
            ->assertJsonPath('site.domain_url', 'https://acmeblog.com');
    }
}
