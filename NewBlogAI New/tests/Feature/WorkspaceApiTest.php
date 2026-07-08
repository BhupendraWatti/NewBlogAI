<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\CustomerManager\Models\Customer;
use App\Modules\CustomerManager\Models\Employee;
use App\Modules\CustomerManager\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceApiTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer1;
    protected Customer $customer2;
    protected User $owner1;
    protected User $writer1;
    protected User $owner2;
    protected Workspace $workspace1;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Customers
        $this->customer1 = Customer::create([
            'company_name' => 'Customer One LLC',
            'owner_name' => 'Alice',
            'email' => 'alice@one.com',
            'status' => 'active',
        ]);

        $this->customer2 = Customer::create([
            'company_name' => 'Customer Two LLC',
            'owner_name' => 'Bob',
            'email' => 'bob@two.com',
            'status' => 'active',
        ]);

        // Create Users for Customer 1
        $this->owner1 = User::create([
            'name' => 'Owner One',
            'email' => 'owner1@one.com',
            'password' => bcrypt('password'),
            'role' => 3,
            'customer_id' => $this->customer1->id,
        ]);

        $this->writer1 = User::create([
            'name' => 'Writer One',
            'email' => 'writer1@one.com',
            'password' => bcrypt('password'),
            'role' => 3,
            'customer_id' => $this->customer1->id,
        ]);

        // Create User for Customer 2
        $this->owner2 = User::create([
            'name' => 'Owner Two',
            'email' => 'owner2@two.com',
            'password' => bcrypt('password'),
            'role' => 3,
            'customer_id' => $this->customer2->id,
        ]);

        // Create Workspace for Customer 1
        $this->workspace1 = Workspace::create([
            'name' => 'Workspace One',
            'customer_id' => $this->customer1->id,
        ]);

        // Add owner1 as Owner of workspace1
        Employee::create([
            'workspace_id' => $this->workspace1->id,
            'user_id' => $this->owner1->id,
            'role' => 'Owner',
        ]);

        // Add writer1 as Writer of workspace1
        Employee::create([
            'workspace_id' => $this->workspace1->id,
            'user_id' => $this->writer1->id,
            'role' => 'Writer',
        ]);
    }

    public function test_tenant_crud_and_auto_owner_on_create(): void
    {
        // 1. Index
        $response = $this->actingAs($this->owner1)->getJson('/api/v1/workspaces');
        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Workspace One']);

        // 2. Show
        $response = $this->actingAs($this->owner1)->getJson("/api/v1/workspaces/{$this->workspace1->id}");
        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Workspace One');

        // 3. Store (Creates a new workspace, auto-assigns creator as Owner)
        $response = $this->actingAs($this->owner1)->postJson('/api/v1/workspaces', [
            'name' => 'New Tenant Workspace',
        ]);
        $response->assertStatus(201);
        $newWorkspaceId = $response->json('data.id');

        $this->assertDatabaseHas('workspaces', [
            'id' => $newWorkspaceId,
            'name' => 'New Tenant Workspace',
            'customer_id' => $this->customer1->id,
        ]);

        $this->assertDatabaseHas('workspace_employees', [
            'workspace_id' => $newWorkspaceId,
            'user_id' => $this->owner1->id,
            'role' => 'Owner',
        ]);

        // 4. Update
        $response = $this->actingAs($this->owner1)->putJson("/api/v1/workspaces/{$this->workspace1->id}", [
            'name' => 'Workspace One Renamed',
        ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('workspaces', [
            'id' => $this->workspace1->id,
            'name' => 'Workspace One Renamed',
        ]);

        // 5. Delete
        $tempWorkspace = Workspace::create([
            'name' => 'Temp Workspace',
            'customer_id' => $this->customer1->id,
        ]);
        Employee::create([
            'workspace_id' => $tempWorkspace->id,
            'user_id' => $this->owner1->id,
            'role' => 'Owner',
        ]);

        $response = $this->actingAs($this->owner1)->deleteJson("/api/v1/workspaces/{$tempWorkspace->id}");
        $response->assertStatus(200);
        $this->assertDatabaseMissing('workspaces', ['id' => $tempWorkspace->id]);
    }

    public function test_cross_tenant_isolation_denied(): void
    {
        // Owner 2 (Customer 2) cannot view Workspace 1 (Customer 1)
        $response = $this->actingAs($this->owner2)->getJson("/api/v1/workspaces/{$this->workspace1->id}");
        $response->assertStatus(403);

        // Owner 2 cannot update Workspace 1
        $response = $this->actingAs($this->owner2)->putJson("/api/v1/workspaces/{$this->workspace1->id}", [
            'name' => 'Hacked Workspace',
        ]);
        $response->assertStatus(403);

        // Owner 2 cannot delete Workspace 1
        $response = $this->actingAs($this->owner2)->deleteJson("/api/v1/workspaces/{$this->workspace1->id}");
        $response->assertStatus(403);
    }

    public function test_writer_cannot_add_employees(): void
    {
        $newUser = User::create([
            'name' => 'New Employee',
            'email' => 'newemp@one.com',
            'password' => bcrypt('password'),
            'role' => 3,
            'customer_id' => $this->customer1->id,
        ]);

        // writer1 (Writer role) tries to add employee
        $response = $this->actingAs($this->writer1)->postJson("/api/v1/workspaces/{$this->workspace1->id}/employees", [
            'user_id' => $newUser->id,
            'role' => 'Editor',
        ]);
        $response->assertStatus(403);
    }

    public function test_cross_customer_user_add_rejected(): void
    {
        // owner1 (Owner) tries to add owner2 (who is in Customer 2) to workspace1 (Customer 1)
        $response = $this->actingAs($this->owner1)->postJson("/api/v1/workspaces/{$this->workspace1->id}/employees", [
            'user_id' => $this->owner2->id,
            'role' => 'Editor',
        ]);
        $response->assertStatus(422);
    }

    public function test_last_owner_removal_rejected(): void
    {
        // Get the employee record of owner1
        $employee = Employee::where('workspace_id', $this->workspace1->id)
            ->where('user_id', $this->owner1->id)
            ->firstOrFail();

        // Try to delete the only Owner (owner1) from workspace1
        $response = $this->actingAs($this->owner1)->deleteJson("/api/v1/workspaces/{$this->workspace1->id}/employees/{$employee->id}");
        $response->assertStatus(422);
        $this->assertDatabaseHas('workspace_employees', ['id' => $employee->id]);
    }
}
