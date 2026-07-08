<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\CustomerManager\Models\Customer;
use App\Modules\CustomerManager\Models\Workspace;
use App\Modules\CustomerManager\Models\Employee;
use App\Modules\SiteManager\Models\Site;
use App\Modules\TopicManager\Models\Topic;
use App\Modules\ContentGeneration\Models\GeneratedContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class WorkspaceEmployeeTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customerA;
    protected Customer $customerB;
    protected Site $siteA;
    protected Site $siteB;
    protected Workspace $workspaceA;
    protected Workspace $workspaceB;
    protected Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Customers
        $this->customerA = Customer::create([
            'company_name' => 'Customer A LLC',
            'owner_name' => 'Alice A',
            'email' => 'alice@customera.com',
            'status' => 'active',
        ]);

        $this->customerB = Customer::create([
            'company_name' => 'Customer B LLC',
            'owner_name' => 'Bob B',
            'email' => 'bob@customerb.com',
            'status' => 'active',
        ]);

        // 2. Create Sites
        $this->siteA = Site::create([
            'customer_id' => $this->customerA->id,
            'name' => 'Site A',
            'domain_url' => 'https://sitea.com',
            'is_active' => true,
        ]);

        $this->siteB = Site::create([
            'customer_id' => $this->customerB->id,
            'name' => 'Site B',
            'domain_url' => 'https://siteb.com',
            'is_active' => true,
        ]);

        // 3. Create Workspaces
        $this->workspaceA = Workspace::create([
            'name' => 'Workspace A',
            'customer_id' => $this->customerA->id,
        ]);
        $this->siteA->update(['workspace_id' => $this->workspaceA->id]);

        $this->workspaceB = Workspace::create([
            'name' => 'Workspace B',
            'customer_id' => $this->customerB->id,
        ]);
        $this->siteB->update(['workspace_id' => $this->workspaceB->id]);

        // 4. Create Topic for GeneratedContent
        $this->topic = Topic::create([
            'name' => 'General Topic',
        ]);
    }

    /**
     * Test workspace creation and user associations.
     */
    public function test_workspace_creation_and_user_associations(): void
    {
        $user = User::create([
            'name' => 'Workspace Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
            'role' => 3, // Non-system-admin
        ]);

        $employee = Employee::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $user->id,
            'role' => 'Owner',
        ]);

        // Check relationship from Workspace
        $this->assertCount(1, $this->workspaceA->employees);
        $this->assertEquals($user->id, $this->workspaceA->employees->first()->user_id);
        $this->assertEquals('Owner', $this->workspaceA->employees->first()->role);

        // Check relationship from Employee
        $this->assertEquals($this->workspaceA->id, $employee->workspace->id);
        $this->assertEquals($user->id, $employee->user->id);

        // Check relationship from User
        $this->assertCount(1, $user->workspaceEmployees);
        $this->assertEquals($this->workspaceA->id, $user->workspaceEmployees->first()->workspace_id);
        $this->assertCount(1, $user->workspaces);
        $this->assertEquals($this->workspaceA->id, $user->workspaces->first()->id);
        $this->assertEquals('Owner', $user->workspaces->first()->pivot->role);

        // Check relationship from Site
        $this->assertEquals($this->workspaceA->id, $this->siteA->workspace->id);
    }

    /**
     * Test workspace isolation: one user cannot access another website's workspace.
     */
    public function test_workspace_isolation(): void
    {
        $userA = User::create([
            'name' => 'User A',
            'email' => 'usera@example.com',
            'password' => bcrypt('password'),
            'role' => 3,
        ]);

        $userB = User::create([
            'name' => 'User B',
            'email' => 'userb@example.com',
            'password' => bcrypt('password'),
            'role' => 3,
        ]);

        Employee::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $userA->id,
            'role' => 'Writer',
        ]);

        Employee::create([
            'workspace_id' => $this->workspaceB->id,
            'user_id' => $userB->id,
            'role' => 'Writer',
        ]);

        // User A should access Workspace A but not Workspace B
        $this->assertTrue(Gate::forUser($userA)->allows('view', $this->workspaceA));
        $this->assertFalse(Gate::forUser($userA)->allows('view', $this->workspaceB));

        // User B should access Workspace B but not Workspace A
        $this->assertTrue(Gate::forUser($userB)->allows('view', $this->workspaceB));
        $this->assertFalse(Gate::forUser($userB)->allows('view', $this->workspaceA));
    }

    /**
     * Test role-based permissions:
     * - Writers cannot review or publish
     * - Reviewers cannot generate or publish
     * - Publishers cannot review
     * - Owners/Admins/Editors can perform all actions
     */
    public function test_role_based_permissions(): void
    {
        // Create generated content linked to Site A (Workspace A)
        $content = GeneratedContent::create([
            'site_id' => $this->siteA->id,
            'topic_id' => $this->topic->id,
            'title' => 'Sample Article',
            'content' => 'Lorem Ipsum dolor sit amet...',
            'status' => 'draft',
        ]);

        // Define users with roles
        $roles = ['Owner', 'Admin', 'Editor', 'Writer', 'Reviewer', 'Publisher'];
        $users = [];

        foreach ($roles as $role) {
            $user = User::create([
                'name' => "User {$role}",
                'email' => strtolower($role) . "@example.com",
                'password' => bcrypt('password'),
                'role' => 3,
            ]);

            Employee::create([
                'workspace_id' => $this->workspaceA->id,
                'user_id' => $user->id,
                'role' => $role,
            ]);

            $users[$role] = $user;
        }

        // Test Writers
        $writer = $users['Writer'];
        $this->assertTrue(Gate::forUser($writer)->allows('viewAny', [GeneratedContent::class, $this->workspaceA]));
        $this->assertTrue(Gate::forUser($writer)->allows('view', $content));
        $this->assertTrue(Gate::forUser($writer)->allows('create', [GeneratedContent::class, $this->workspaceA]));
        $this->assertTrue(Gate::forUser($writer)->allows('generate', [GeneratedContent::class, $this->workspaceA]));
        $this->assertFalse(Gate::forUser($writer)->allows('review', $content));
        $this->assertFalse(Gate::forUser($writer)->allows('publish', $content));

        // Test Reviewers
        $reviewer = $users['Reviewer'];
        $this->assertTrue(Gate::forUser($reviewer)->allows('viewAny', [GeneratedContent::class, $this->workspaceA]));
        $this->assertTrue(Gate::forUser($reviewer)->allows('view', $content));
        $this->assertFalse(Gate::forUser($reviewer)->allows('create', [GeneratedContent::class, $this->workspaceA]));
        $this->assertFalse(Gate::forUser($reviewer)->allows('generate', [GeneratedContent::class, $this->workspaceA]));
        $this->assertTrue(Gate::forUser($reviewer)->allows('review', $content));
        $this->assertFalse(Gate::forUser($reviewer)->allows('publish', $content));

        // Test Publishers
        $publisher = $users['Publisher'];
        $this->assertTrue(Gate::forUser($publisher)->allows('viewAny', [GeneratedContent::class, $this->workspaceA]));
        $this->assertTrue(Gate::forUser($publisher)->allows('view', $content));
        $this->assertFalse(Gate::forUser($publisher)->allows('create', [GeneratedContent::class, $this->workspaceA]));
        $this->assertFalse(Gate::forUser($publisher)->allows('generate', [GeneratedContent::class, $this->workspaceA]));
        $this->assertFalse(Gate::forUser($publisher)->allows('review', $content));
        $this->assertTrue(Gate::forUser($publisher)->allows('publish', $content));

        // Test Owners/Admins/Editors (can do everything)
        foreach (['Owner', 'Admin', 'Editor'] as $powerRole) {
            $powerUser = $users[$powerRole];
            $this->assertTrue(Gate::forUser($powerUser)->allows('viewAny', [GeneratedContent::class, $this->workspaceA]));
            $this->assertTrue(Gate::forUser($powerUser)->allows('view', $content));
            $this->assertTrue(Gate::forUser($powerUser)->allows('create', [GeneratedContent::class, $this->workspaceA]));
            $this->assertTrue(Gate::forUser($powerUser)->allows('generate', [GeneratedContent::class, $this->workspaceA]));
            $this->assertTrue(Gate::forUser($powerUser)->allows('review', $content));
            $this->assertTrue(Gate::forUser($powerUser)->allows('publish', $content));
        }
    }
}
