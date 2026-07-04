<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Operations\Models\AuditLog;
use App\Modules\Operations\Models\JobLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OperationsMonitoringTest extends TestCase
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

    public function test_system_health_diagnostic_endpoint(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'healthy')
            ->assertJsonPath('database.status', 'healthy')
            ->assertJsonPath('cache.status', 'healthy')
            ->assertJsonPath('storage.status', 'healthy');
    }

    public function test_admin_can_retrieve_analytics(): void
    {
        // 1. AI Stats
        $response1 = $this->actingAs($this->admin)
            ->getJson('/api/v1/analytics/ai');

        $response1->assertStatus(200)
            ->assertJsonPath('total_requests', 0)
            ->assertJsonPath('total_cost', 0);

        // 2. Content Stats
        $response2 = $this->actingAs($this->admin)
            ->getJson('/api/v1/analytics/content');

        $response2->assertStatus(200)
            ->assertJsonPath('total_articles', 0);
    }

    public function test_queue_monitoring_records_jobs_automatically(): void
    {
        // Setup a mock job log in DB to verify tracking list
        JobLog::create([
            'job_id' => 'mock-id-123',
            'name' => 'SyncSiteDataJob',
            'queue' => 'default',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/operations/jobs');

        $response->assertStatus(200)
            ->assertJsonFragment(['job_id' => 'mock-id-123']);
    }

    public function test_manual_configuration_audit_logging(): void
    {
        AuditLog::create([
            'user_id' => $this->admin->id,
            'event' => 'settings_updated',
            'old_values' => ['currency' => 'USD'],
            'new_values' => ['currency' => 'INR'],
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/operations/audit');

        $response->assertStatus(200)
            ->assertJsonFragment(['event' => 'settings_updated']);
    }

    public function test_scheduler_commands_exist(): void
    {
        // Verify we can run schedule list or dry-run without exception
        $exitCode = Artisan::call('schedule:list');
        $this->assertEquals(0, $exitCode);
    }
}
