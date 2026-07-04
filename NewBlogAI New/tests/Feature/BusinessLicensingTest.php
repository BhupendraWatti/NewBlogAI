<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\CustomerManager\Models\Customer;
use App\Modules\Licensing\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessLicensingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Customer $customer;

    protected LicenseService $licenseService;

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

        $this->customer = Customer::create([
            'company_name' => 'Wernham Hogg',
            'owner_name' => 'David Brent',
            'email' => 'david@wernhamhogg.com',
            'status' => 'active',
        ]);

        $this->licenseService = resolve(LicenseService::class);
    }

    public function test_plugin_license_lifecycle(): void
    {
        // 1. Generate License
        $license = $this->licenseService->generateLicense($this->customer->id, 1);

        $this->assertDatabaseHas('plugin_licenses', [
            'license_key' => $license->license_key,
            'status' => 'inactive',
        ]);

        // 2. Activate License via public REST API
        $response = $this->postJson('/api/v1/license/activate', [
            'license_key' => $license->license_key,
            'domain' => 'https://dundermifflin.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('plugin_licenses', [
            'license_key' => $license->license_key,
            'domain' => 'https://dundermifflin.com',
            'status' => 'active',
        ]);

        // 3. Verify License
        $responseVerify = $this->postJson('/api/v1/license/verify', [
            'license_key' => $license->license_key,
            'domain' => 'https://dundermifflin.com',
        ]);

        $responseVerify->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // Verify domain mismatch failure
        $responseVerifyFail = $this->postJson('/api/v1/license/verify', [
            'license_key' => $license->license_key,
            'domain' => 'https://wernhamhogg.com',
        ]);

        $responseVerifyFail->assertStatus(403);

        // 4. Deactivate License
        $responseDeactivate = $this->postJson('/api/v1/license/deactivate', [
            'license_key' => $license->license_key,
            'domain' => 'https://dundermifflin.com',
        ]);

        $responseDeactivate->assertStatus(200);

        $this->assertDatabaseHas('plugin_licenses', [
            'license_key' => $license->license_key,
            'domain' => null,
            'status' => 'inactive',
        ]);
    }

    public function test_admin_can_perform_user_management_crud(): void
    {
        // 1. Create User
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/users', [
                'name' => 'Dwight Schrute',
                'email' => 'dwight@dundermifflin.com',
                'password' => 'assistant-regional-manager',
                'role' => 4, // User
            ]);

        $response->assertStatus(201);
        $userId = $response->json('data.id');

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'email' => 'dwight@dundermifflin.com',
        ]);

        // Verify action is audited
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user_created',
        ]);

        // 2. Update User
        $responseUpdate = $this->actingAs($this->admin)
            ->putJson("/api/v1/users/{$userId}", [
                'name' => 'Dwight Schrute (Acting Manager)',
            ]);

        $responseUpdate->assertStatus(200);
        $this->assertEquals('Dwight Schrute (Acting Manager)', User::find($userId)->name);

        // Verify update is audited
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user_updated',
        ]);

        // 3. Delete User
        $responseDelete = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/users/{$userId}");

        $responseDelete->assertStatus(200);
        $this->assertNull(User::find($userId));

        // Verify delete is audited
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user_deleted',
        ]);
    }
}
