<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\CustomerManager\Models\Customer;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\SiteManager\Models\Site;
use App\Modules\SubscriptionManager\Models\Plan;
use App\Modules\SubscriptionManager\Models\Subscription;
use App\Modules\TopicManager\Models\Topic;
use App\Modules\ScheduleManager\Models\PublishingSchedule;
use App\Modules\ScheduleManager\Services\ScheduleService;
use App\Modules\ScheduleManager\Services\ContentCalendarService;
use App\Modules\Publishing\Models\PublishingLog;
use App\Modules\Operations\Models\ScheduleLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ContentCalendarSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected ScheduleService $scheduleService;
    protected ContentCalendarService $calendarService;
    protected Site $site;
    protected ContentPipeline $pipeline;
    protected Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->scheduleService = resolve(ScheduleService::class);
        $this->calendarService = resolve(ContentCalendarService::class);

        $customer = Customer::create([
            'company_name' => 'Acme Corp',
            'owner_name' => 'Alice owner',
            'email' => 'alice@acme.com',
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'name' => 'Premium Plan',
            'monthly_price' => 49.00,
            'yearly_price' => 490.00,
            'max_wordpress_sites' => 3,
            'max_topics' => 10,
            'publishing_schedule_limit' => 5,
            'max_articles_per_day' => 10,
            'prompt_templates_allowed' => 5,
            'ai_providers_available' => ['openai'],
            'api_keys_allowed' => 3,
            'storage_limit' => 2048,
            'status' => 'active',
            'minimum_publishing_frequency' => 'daily',
            'feature_flags' => ['seo_optimizer' => true],
        ]);

        Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_period' => 'monthly',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'limits' => $plan->toArray(),
        ]);

        $this->site = Site::create([
            'customer_id' => $customer->id,
            'domain_url' => 'https://acmeblog.com',
            'name' => 'Acme Blog',
            'api_key' => 'wp_app_password_value',
            'is_active' => true,
            'status' => 'connected',
            'timezone' => 'UTC',
        ]);

        $this->topic = Topic::create([
            'name' => 'AI and Future',
            'category' => 'AI',
            'status' => 'active',
        ]);

        $prompt = Prompt::create([
            'name' => 'AI Standard Prompt',
            'prompt' => 'Write about AI.',
            'category' => 'AI',
            'status' => 'active',
        ]);

        $provider = AIProvider::create([
            'provider_key' => 'openai',
            'name' => 'OpenAI',
            'api_key' => 'some-encrypted-key',
            'default_model' => 'gpt-4o',
            'is_enabled' => true,
        ]);

        $this->pipeline = ContentPipeline::create([
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'prompt_id' => $prompt->id,
            'ai_provider_id' => $provider->id,
            'language' => 'en',
            'generation_type' => 'article',
            'is_active' => true,
        ]);
    }

    public function test_calendar_events_group_and_ranges()
    {
        // 1. Create articles in different states
        // Approved/Pending review
        $pendingArticle = new GeneratedContent([
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'title' => 'Pending AI Article',
            'content' => 'Content...',
            'status' => 'pending_review',
        ]);
        $pendingArticle->timestamps = false;
        $pendingArticle->created_at = Carbon::parse('2026-07-10 10:00:00');
        $pendingArticle->save();

        // Published
        $publishedArticle = new GeneratedContent([
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'title' => 'Published AI Article',
            'content' => 'Content...',
            'status' => 'published',
        ]);
        $publishedArticle->timestamps = false;
        $publishedArticle->created_at = Carbon::parse('2026-07-05 09:00:00');
        $publishedArticle->save();

        $pubLog = PublishingLog::create([
            'generated_content_id' => $publishedArticle->id,
            'site_id' => $this->site->id,
            'status' => 'completed',
            'completed_at' => Carbon::parse('2026-07-05 09:30:00'),
        ]);

        // Scheduled
        $scheduledArticle = new GeneratedContent([
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'title' => 'Scheduled AI Article',
            'content' => 'Content...',
            'status' => 'approved',
        ]);
        $scheduledArticle->timestamps = false;
        $scheduledArticle->created_at = Carbon::parse('2026-07-08 12:00:00');
        $scheduledArticle->save();

        $schedLog = PublishingLog::create([
            'generated_content_id' => $scheduledArticle->id,
            'site_id' => $this->site->id,
            'status' => 'pending',
            'scheduled_at' => Carbon::parse('2026-07-12 15:00:00'),
        ]);

        // 2. Create active schedule (daily, time_based)
        $schedule = PublishingSchedule::create([
            'site_id' => $this->site->id,
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Daily AI Schedule',
            'frequency' => 'daily',
            'timezone' => 'UTC',
            'time_of_day' => '09:00:00',
            'is_active' => true,
            'schedule_mode' => 'time_based',
            'next_run_at' => Carbon::parse('2026-07-09 09:00:00'),
        ]);

        // 3. Query calendar events for range: 2026-07-01 to 2026-07-15
        $events = $this->calendarService->getCalendarEvents(
            $this->site->id,
            '2026-07-01 00:00:00',
            '2026-07-15 23:59:59'
        );

        // Verify we got the events and they are sorted chronologically
        $this->assertNotEmpty($events);

        // Let's assert sorting order
        $previousDate = null;
        foreach ($events as $event) {
            if ($previousDate) {
                $this->assertGreaterThanOrEqual($previousDate, $event['date']);
            }
            $previousDate = $event['date'];
        }

        // Verify we have our articles mapped
        $types = collect($events)->pluck('type')->unique();
        $this->assertTrue($types->contains('article'));
        $this->assertTrue($types->contains('schedule'));

        // Let's check specific statuses
        $statuses = collect($events)->pluck('status')->toArray();
        $this->assertContains('pending_review', $statuses);
        $this->assertContains('published', $statuses);
        $this->assertContains('scheduled', $statuses);
        $this->assertContains('predicted', $statuses);

        // Check date range limits: no events outside the range should be included
        // Create an article way in the future
        $futureArticle = new GeneratedContent([
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'title' => 'Future Article',
            'content' => 'Content...',
            'status' => 'pending_review',
        ]);
        $futureArticle->timestamps = false;
        $futureArticle->created_at = Carbon::parse('2026-08-01 10:00:00');
        $futureArticle->save();

        $eventsLimited = $this->calendarService->getCalendarEvents(
            $this->site->id,
            '2026-07-01 00:00:00',
            '2026-07-15 23:59:59'
        );
        $limitedTitles = collect($eventsLimited)->pluck('title')->toArray();
        $this->assertNotContains('Future Article', $limitedTitles);
    }

    public function test_scheduler_run_mode_time_based()
    {
        // 1. Create a schedule that is due (next_run_at in the past)
        $schedule = PublishingSchedule::create([
            'site_id' => $this->site->id,
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Time-based Due Schedule',
            'frequency' => 'daily',
            'timezone' => 'UTC',
            'time_of_day' => '09:00:00',
            'is_active' => true,
            'schedule_mode' => 'time_based',
            'next_run_at' => Carbon::now()->subHour(),
        ]);

        $processed = $this->scheduleService->runDue();

        $this->assertEquals(1, $processed);

        // Check log exists
        $this->assertDatabaseHas('schedule_logs', [
            'task_name' => "Publishing Schedule #{$schedule->id} ({$schedule->name})",
            'status' => 'success',
        ]);

        // Check next_run_at updated
        $schedule->refresh();
        $this->assertNotNull($schedule->last_run_at);
        $this->assertTrue($schedule->next_run_at->isAfter(Carbon::now()));
    }

    public function test_scheduler_run_mode_coverage_based_triggers_when_empty()
    {
        // Category is "AI". There are no articles in category "AI" (empty).
        // Create coverage-based schedule
        $schedule = PublishingSchedule::create([
            'site_id' => $this->site->id,
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Coverage-based Schedule',
            'frequency' => 'daily',
            'timezone' => 'UTC',
            'time_of_day' => '09:00:00',
            'is_active' => true,
            'schedule_mode' => 'coverage_based',
            'next_run_at' => Carbon::now()->subHour(),
        ]);

        $processed = $this->scheduleService->runDue();

        // Should trigger since category is empty
        $this->assertEquals(1, $processed);

        $schedule->refresh();
        $this->assertNotNull($schedule->last_run_at);
        $this->assertTrue($schedule->next_run_at->isAfter(Carbon::now()));
    }

    public function test_scheduler_run_mode_coverage_based_skips_when_fresh()
    {
        // Create an article generated today, making the "AI" category fresh
        $article = new GeneratedContent([
            'site_id' => $this->site->id,
            'pipeline_id' => $this->pipeline->id,
            'title' => 'Recent Article',
            'content' => 'Content',
            'status' => 'published',
        ]);
        $article->save();

        // Create coverage-based schedule
        $schedule = PublishingSchedule::create([
            'site_id' => $this->site->id,
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Coverage-based Schedule Fresh',
            'frequency' => 'daily',
            'timezone' => 'UTC',
            'time_of_day' => '09:00:00',
            'is_active' => true,
            'schedule_mode' => 'coverage_based',
            'next_run_at' => Carbon::now()->subHour(),
        ]);

        $processed = $this->scheduleService->runDue();

        // Should skip since category is fresh
        $this->assertEquals(0, $processed);

        $schedule->refresh();
        // last_run_at should still be null because it was skipped
        $this->assertNull($schedule->last_run_at);
        // next_run_at should still be updated (to prevent tight loop)
        $this->assertTrue($schedule->next_run_at->isAfter(Carbon::now()));
    }

    public function test_scheduler_run_mode_event_based_does_not_run_automatically()
    {
        $schedule = PublishingSchedule::create([
            'site_id' => $this->site->id,
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Event-based Schedule',
            'frequency' => 'daily',
            'timezone' => 'UTC',
            'time_of_day' => '09:00:00',
            'is_active' => true,
            'schedule_mode' => 'event_based',
            'next_run_at' => Carbon::now()->subHour(),
        ]);

        $processed = $this->scheduleService->runDue();

        // Event-based schedules should NOT run during automatic runDue
        $this->assertEquals(0, $processed);

        // Manually trigger the schedule run
        $this->scheduleService->triggerManualRun($schedule);

        $schedule->refresh();
        $this->assertNotNull($schedule->last_run_at);

        $this->assertDatabaseHas('schedule_logs', [
            'task_name' => "Manual Schedule Run #{$schedule->id} ({$schedule->name})",
            'status' => 'success',
        ]);
    }
}
