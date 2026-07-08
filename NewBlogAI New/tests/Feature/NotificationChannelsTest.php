<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\SiteManager\Models\Site;
use App\Modules\TopicManager\Models\Topic;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\Operations\Notifications\AIGenerationFailedNotification;
use App\Modules\Operations\Notifications\Channels\DiscordWebhookChannel;
use App\Modules\Operations\Notifications\Channels\GenericWebhookChannel;
use App\Modules\Operations\Notifications\Channels\SlackWebhookChannel;
use App\Modules\SystemSettings\Services\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationChannelsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected SystemSettingsService $settings;
    protected ContentPipeline $pipeline;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
            'role' => 3, // Editor
        ]);

        $site = Site::create([
            'domain_url' => 'https://example-test.com',
            'api_key' => 'test-token',
            'is_active' => true,
        ]);

        $topic = Topic::create([
            'name' => 'Laravel 12 Features',
            'category' => 'Tech',
            'language' => 'en',
            'status' => 'active',
            'generation_frequency' => 'daily',
        ]);

        $prompt = Prompt::create([
            'name' => 'Test Prompt',
            'prompt' => 'Write about {{topic}}',
            'category' => 'Tech',
            'status' => 'active',
        ]);

        $provider = AIProvider::create([
            'provider_key' => 'gemini',
            'name' => 'Google Gemini',
            'api_key' => 'some-api-key',
            'default_model' => 'gemini-2.5-flash',
            'is_enabled' => true,
        ]);

        $this->pipeline = ContentPipeline::create([
            'site_id' => $site->id,
            'topic_id' => $topic->id,
            'prompt_id' => $prompt->id,
            'ai_provider_id' => $provider->id,
            'language' => 'en',
            'generation_type' => 'article',
            'is_active' => true,
        ]);

        $this->settings = app(SystemSettingsService::class);
        $this->settings->set(SlackWebhookChannel::SETTING_KEY, null);
        $this->settings->set(DiscordWebhookChannel::SETTING_KEY, null);
        $this->settings->set(GenericWebhookChannel::SETTING_KEY, null);
    }

    public function test_via_returns_default_channels_when_no_webhooks_configured(): void
    {
        $run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'failed',
            'error_message' => 'API limit exceeded',
        ]);

        $notification = new AIGenerationFailedNotification($run);
        $channels = $notification->via($this->user);

        $this->assertEquals(['database', 'mail'], $channels);
    }

    public function test_via_returns_webhook_channels_when_configured(): void
    {
        $this->settings->set(SlackWebhookChannel::SETTING_KEY, 'https://hooks.slack.com/services/test');
        $this->settings->set(DiscordWebhookChannel::SETTING_KEY, 'https://discord.com/api/webhooks/test');
        $this->settings->set(GenericWebhookChannel::SETTING_KEY, 'https://example.com/webhook');

        $run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'failed',
            'error_message' => 'API limit exceeded',
        ]);

        $notification = new AIGenerationFailedNotification($run);
        $channels = $notification->via($this->user);

        $this->assertContains('database', $channels);
        $this->assertContains('mail', $channels);
        $this->assertContains(SlackWebhookChannel::class, $channels);
        $this->assertContains(DiscordWebhookChannel::class, $channels);
        $this->assertContains(GenericWebhookChannel::class, $channels);
    }

    public function test_slack_webhook_sends_payload_successfully_and_ignores_failures(): void
    {
        Http::fake([
            'https://hooks.slack.com/*' => Http::response('ok', 200),
        ]);

        $this->settings->set(SlackWebhookChannel::SETTING_KEY, 'https://hooks.slack.com/services/test');

        $channel = app(SlackWebhookChannel::class);
        $run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'failed',
            'error_message' => 'API limit exceeded',
        ]);
        $notification = new AIGenerationFailedNotification($run);

        $channel->send($this->user, $notification);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://hooks.slack.com/services/test'
                && isset($request['text'])
                && str_contains($request['text'], 'Ai Generation Failed')
                && str_contains($request['text'], 'API limit exceeded');
        });

        // Test delivery failure does not throw exception
        Http::fake([
            'https://hooks.slack.com/*' => Http::response('error', 500),
        ]);

        $channel->send($this->user, $notification);
        $this->assertTrue(true); // Assert no exception thrown
    }

    public function test_discord_webhook_sends_payload_successfully_and_ignores_failures(): void
    {
        Http::fake([
            'https://discord.com/*' => Http::response('ok', 204),
        ]);

        $this->settings->set(DiscordWebhookChannel::SETTING_KEY, 'https://discord.com/api/webhooks/test');

        $channel = app(DiscordWebhookChannel::class);
        $run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'failed',
            'error_message' => 'API limit exceeded',
        ]);
        $notification = new AIGenerationFailedNotification($run);

        $channel->send($this->user, $notification);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://discord.com/api/webhooks/test'
                && isset($request['content'])
                && str_contains($request['content'], 'Ai Generation Failed')
                && strlen($request['content']) <= 2000;
        });

        // Test delivery failure does not throw exception
        Http::fake([
            'https://discord.com/*' => Http::response('error', 500),
        ]);

        $channel->send($this->user, $notification);
        $this->assertTrue(true); // Assert no exception thrown
    }

    public function test_generic_webhook_sends_payload_successfully_and_ignores_failures(): void
    {
        Http::fake([
            'https://example.com/webhook' => Http::response('ok', 200),
        ]);

        $this->settings->set(GenericWebhookChannel::SETTING_KEY, 'https://example.com/webhook');

        $channel = app(GenericWebhookChannel::class);
        $run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'failed',
            'error_message' => 'API limit exceeded',
        ]);
        $notification = new AIGenerationFailedNotification($run);

        $channel->send($this->user, $notification);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/webhook'
                && $request['event'] === 'ai_generation_failed'
                && isset($request['data']['pipeline_run_id'])
                && isset($request['sent_at']);
        });

        // Test delivery failure does not throw exception
        Http::fake([
            'https://example.com/webhook' => Http::response('error', 500),
        ]);

        $channel->send($this->user, $notification);
        $this->assertTrue(true); // Assert no exception thrown
    }

    public function test_notifications_feed_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/notifications')->assertStatus(401);
        $this->getJson('/api/v1/notifications/unread-count')->assertStatus(401);
        $this->postJson('/api/v1/notifications/read-all')->assertStatus(401);
        $this->postJson('/api/v1/notifications/any-id/read')->assertStatus(401);
    }

    public function test_can_list_and_mark_notifications(): void
    {
        $run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'failed',
            'error_message' => 'API limit exceeded',
        ]);
        $notification = new AIGenerationFailedNotification($run);

        // Notify user to populate notifications table
        $this->user->notify($notification);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/notifications')
            ->assertStatus(200);

        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'type', 'data', 'read_at', 'created_at']
            ],
            'first_page_url', 'next_page_url', 'prev_page_url', 'path', 'per_page', 'to', 'total'
        ]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/notifications/unread-count')
            ->assertStatus(200)
            ->assertJson(['unread_count' => 1]);

        // Filter unread
        $this->actingAs($this->user)
            ->getJson('/api/v1/notifications?unread=1')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $notificationId = $this->user->unreadNotifications->first()->id;

        // Mark single as read
        $this->actingAs($this->user)
            ->postJson("/api/v1/notifications/{$notificationId}/read")
            ->assertStatus(200);

        $this->actingAs($this->user)
            ->getJson('/api/v1/notifications/unread-count')
            ->assertStatus(200)
            ->assertJson(['unread_count' => 0]);
    }

    public function test_can_mark_all_as_read(): void
    {
        $run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'failed',
            'error_message' => 'API limit exceeded',
        ]);
        $notification = new AIGenerationFailedNotification($run);

        $this->user->notify($notification);
        $this->user->notify($notification);

        $this->actingAs($this->user)
            ->getJson('/api/v1/notifications/unread-count')
            ->assertStatus(200)
            ->assertJson(['unread_count' => 2]);

        $this->actingAs($this->user)
            ->postJson('/api/v1/notifications/read-all')
            ->assertStatus(200);

        $this->actingAs($this->user)
            ->getJson('/api/v1/notifications/unread-count')
            ->assertStatus(200)
            ->assertJson(['unread_count' => 0]);
    }

    public function test_user_cannot_mark_other_users_notification_as_read(): void
    {
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
            'role' => 3,
        ]);

        $run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'failed',
            'error_message' => 'API limit exceeded',
        ]);
        $notification = new AIGenerationFailedNotification($run);

        $otherUser->notify($notification);
        $notificationId = $otherUser->unreadNotifications->first()->id;

        // User attempting to mark other user's notification as read should receive 404
        $this->actingAs($this->user)
            ->postJson("/api/v1/notifications/{$notificationId}/read")
            ->assertStatus(404);
    }
}
