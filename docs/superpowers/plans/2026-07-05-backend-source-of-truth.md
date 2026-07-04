# Backend Source of Truth Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Laravel the authoritative owner of subscriptions, entitlements, website configuration, scheduling, generation, and publishing while preserving the current WordPress plugin contract.

**Architecture:** SubscriptionManager exposes one entitlement interface used by every restricted operation. SiteManager composes the final versioned execution configuration consumed by WordPress, while ScheduleManager owns recurring execution and delegates actual runs to ContentPipeline. Existing `/api/plugin/*` paths remain compatibility aliases for canonical `/api/v1/plugin/*` endpoints.

**Tech Stack:** PHP 8.2, Laravel 12, Eloquent, Laravel queues/scheduler, PHPUnit 11, MySQL/SQLite-compatible migrations.

---

### Task 1: Define module ownership

**Files:**
- Create: `.ai/MODULE_OWNERSHIP.md`
- Modify: `.ai/ARCHITECTURE.md`
- Modify: `.ai/BUSINESS_RULES.md`

- [ ] Record one owner for customers, subscriptions, sites, topics, prompts, providers, pipelines, schedules, generated content, publishing, analytics, licensing, restrictions, permissions, and system configuration.
- [ ] Document permitted module dependencies and the plugin's execution-only role.

### Task 2: Add an entitlement interface

**Files:**
- Create: `NewBlogAI New/app/Modules/SubscriptionManager/Exceptions/EntitlementDeniedException.php`
- Create: `NewBlogAI New/app/Modules/SubscriptionManager/Services/EntitlementService.php`
- Modify: `NewBlogAI New/app/Modules/SubscriptionManager/Models/Plan.php`
- Modify: `NewBlogAI New/app/Modules/SubscriptionManager/Models/Subscription.php`

- [ ] Add failing tests for website, topic, provider, monthly generation, publishing, schedule, and feature limits.
- [ ] Implement active-subscription resolution, normalized entitlement snapshots, usage calculation, and assertion methods.
- [ ] Verify denials return structured HTTP 422 responses without leaking implementation details.

### Task 3: Extend the product schema safely

**Files:**
- Create: `NewBlogAI New/database/migrations/2026_07_05_000001_add_product_entitlements_and_tenancy.php`
- Create: `NewBlogAI New/database/migrations/2026_07_05_000002_create_publishing_schedules_table.php`
- Create: `NewBlogAI New/database/migrations/2026_07_05_000003_secure_plugin_credentials.php`

- [ ] Add nullable ownership columns first so old code and existing data remain valid.
- [ ] Add JSON feature flags, monthly generation limits, publishing frequency, site configuration fields, and usage attribution.
- [ ] Add schedule storage with site and pipeline foreign keys.
- [ ] Add token hashes and lifecycle metadata while retaining the legacy token column during migration.
- [ ] Test migrate, rollback, and migrate again on SQLite.

### Task 4: Make site configuration authoritative

**Files:**
- Create: `NewBlogAI New/app/Modules/SiteManager/Services/SiteConfigurationService.php`
- Modify: `NewBlogAI New/app/Modules/SiteManager/Models/Site.php`
- Modify: `NewBlogAI New/app/Modules/SiteManager/Services/WPClientService.php`

- [ ] Build a versioned configuration containing site state, topics, schedules, publishing mode, category mapping, subscription status, entitlements, provider selection, and synchronization metadata.
- [ ] Keep legacy `selected_topics` and `slot` keys in the payload.
- [ ] Ensure no AI credentials or WordPress credentials appear in configuration responses.
- [ ] Make push synchronization send the same configuration returned by pull synchronization.

### Task 5: Move recurring execution into Laravel

**Files:**
- Create: `NewBlogAI New/app/Modules/ScheduleManager/Models/PublishingSchedule.php`
- Create: `NewBlogAI New/app/Modules/ScheduleManager/Services/ScheduleService.php`
- Create: `NewBlogAI New/app/Modules/ScheduleManager/Controllers/ScheduleController.php`
- Create: `NewBlogAI New/app/Modules/ScheduleManager/Requests/StoreScheduleRequest.php`
- Create: `NewBlogAI New/app/Modules/ScheduleManager/Resources/ScheduleResource.php`
- Modify: `NewBlogAI New/routes/console.php`
- Modify: `NewBlogAI New/routes/api.php`

- [ ] Add CRUD operations that enforce per-plan schedule capacity.
- [ ] Calculate the next run in the site's timezone.
- [ ] Run due schedules every minute with row locking and delegate execution to PipelineService.
- [ ] Keep WordPress cron limited to configuration refresh and heartbeat behavior.

### Task 6: Enforce ownership at every restricted operation

**Files:**
- Modify: `NewBlogAI New/app/Modules/SiteManager/Services/SiteService.php`
- Modify: `NewBlogAI New/app/Modules/TopicManager/Services/TopicService.php`
- Modify: `NewBlogAI New/app/Modules/ContentPipeline/Services/PipelineService.php`
- Modify: `NewBlogAI New/app/Modules/ContentGeneration/Services/ContentGenerationService.php`
- Modify: `NewBlogAI New/app/Modules/Publishing/Services/PublishingService.php`
- Modify: `NewBlogAI New/app/Modules/Licensing/Services/LicenseService.php`

- [ ] Enforce site capacity during website registration and creation.
- [ ] Enforce topic capacity and subscription ownership when topics are tenant-owned.
- [ ] Require pipeline site/topic/prompt ownership consistency and allowed providers.
- [ ] Reserve monthly generation capacity before contacting an AI provider.
- [ ] Enforce publishing frequency and daily publishing limits before queue dispatch.
- [ ] Derive license installation capacity from the subscription where applicable.

### Task 7: Consolidate plugin-facing endpoints

**Files:**
- Create: `NewBlogAI New/app/Modules/SiteManager/Services/PluginTokenService.php`
- Modify: `NewBlogAI New/app/Modules/SiteManager/Controllers/WPPluginAPIController.php`
- Modify: `NewBlogAI New/routes/api.php`

- [ ] Store new plugin tokens by hash and rotate/revoke them centrally.
- [ ] Scope site lookup to the authenticated customer's websites.
- [ ] Return authoritative site configuration and real subscription/provider status.
- [ ] Register canonical `/api/v1/plugin/*` routes and preserve `/api/plugin/*` aliases.
- [ ] Replace placeholder route closures with controller operations or remove them.

### Task 8: Verify architecture and compatibility

**Files:**
- Create: `NewBlogAI New/tests/Feature/BackendSourceOfTruthTest.php`
- Modify: existing affected feature tests only where a newly explicit contract requires it.

- [ ] Run focused entitlement, configuration, scheduling, plugin, pipeline, generation, and publishing tests.
- [ ] Run `php artisan test` and expect all tests to pass.
- [ ] Run `vendor/bin/pint --test` on changed PHP files and resolve formatting issues.
- [ ] Update `.ai/TASK_STATE.md`, `.ai/CHANGELOG_AI.md`, and `.ai/DECISIONS.md`.
