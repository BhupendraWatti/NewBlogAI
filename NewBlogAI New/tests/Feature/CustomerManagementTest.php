<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\CustomerManager\Models\Customer;
use App\Modules\CustomerManager\Models\CustomerNote;
use App\Modules\CustomerManager\Models\CustomerActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $supportUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Super Admin (role 1)
        $this->superAdmin = User::create([
            'name'     => 'Super Admin',
            'email'    => 'super@newsblogify.com',
            'password' => bcrypt('password'),
        ]);
        $this->superAdmin->role = 1;
        $this->superAdmin->save();

        // Create Support Staff (role 3)
        $this->supportUser = User::create([
            'name'     => 'Support Staff',
            'email'    => 'support@newsblogify.com',
            'password' => bcrypt('password'),
        ]);
        $this->supportUser->role = 3;
        $this->supportUser->save();
    }

    /**
     * Test: Super Admin can register a new Customer record.
     */
    public function test_super_admin_can_create_customer(): void
    {
        $payload = [
            'company_name' => 'Acme Corp',
            'owner_name'   => 'John Doe',
            'email'        => 'john@acme.com',
            'phone'        => '+1234567890',
            'country'      => 'USA',
            'website'      => 'https://acme.com',
            'status'       => 'trial',
            'notes'        => 'Acme onboarding started.'
        ];

        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/v1/customers', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.company_name', 'Acme Corp');

        $this->assertDatabaseHas('customers', [
            'company_name' => 'Acme Corp',
            'email'        => 'john@acme.com'
        ]);

        // Assert Note was recorded
        $this->assertDatabaseHas('customer_notes', [
            'content' => 'Acme onboarding started.'
        ]);

        // Assert audit log was recorded
        $this->assertDatabaseHas('customer_activities', [
            'event_type' => 'created'
        ]);
    }

    /**
     * Test: Support cannot create a Customer (policy block).
     */
    public function test_support_cannot_create_customer(): void
    {
        $payload = [
            'company_name' => 'Acme Corp',
            'owner_name'   => 'John Doe',
            'email'        => 'john@acme.com'
        ];

        $response = $this->actingAs($this->supportUser)
            ->postJson('/api/v1/customers', $payload);

        $response->assertStatus(403);
    }

    /**
     * Test: Soft delete and restore lifecycle.
     */
    public function test_customer_soft_delete_and_restore(): void
    {
        $customer = Customer::create([
            'company_name' => 'Legacy Inc',
            'owner_name'   => 'Alice Smith',
            'email'        => 'alice@legacy.com',
            'status'       => 'active'
        ]);

        // Super Admin deletes customer
        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/v1/customers/{$customer->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);

        // Super Admin restores customer
        $restoreResponse = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/customers/{$customer->id}/restore");

        $restoreResponse->assertStatus(200);
        $this->assertDatabaseHas('customers', [
            'id'         => $customer->id,
            'deleted_at' => null
        ]);
    }
}
