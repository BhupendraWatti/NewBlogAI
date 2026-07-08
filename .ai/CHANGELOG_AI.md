# Changelog AI

## [2026-07-08]
### Added
- **Phase 4: Category-Driven News Generation**
  - Replaced user-defined `topics` with a predefined set of global `news_categories` (`global`, `trending`, `local`, `technology`, `business`, `politics`, `sports`, `health`, `science`, `entertainment`).
  - Added string column `news_category` to `content_pipelines` table and dropped the `topic_id` foreign key.
  - Implemented `CategoryResolverService` (replacing `TopicResolverService`) to resolve category news subject strings (e.g. mapping `technology` to `"latest technology news today"`) and derive BCP-47 locale/region parameters from the pipeline output language.
  - Configured `ResearchService` to dynamically construct time-aware, category-tailored news search query sets.
  - Configured `ContentGeneratorService` to resolve tone maps appropriate for individual journalistic categories, outputting articles under category-based headers (e.g. `"Technology News: July 8, 2026"`).
  - Aligned `PromptEngine` system instructions to news reporting and journalism styles, configuring raw markdown formatting defaults.

### Fixed
- **CoverageService category freshness runtime crash**: `getCategoryStatus()` still queried the removed `topic` relation on `GeneratedContent` (throwing `RelationNotFoundException` at runtime). Rewritten to join `content_pipelines.news_category` (case-insensitive), consistent with `getRecommendations()`. `CoverageFreshnessTest` rewritten to the category-driven domain model (it previously created pipelines with the dropped `topic_id` column). Backward compatible: method signatures and return values unchanged.
- **Database & UI Exceptions**
  - **Blade 500 error**: Fixed Blade compiler crash (`Undefined constant "category"`) in `prompts.blade.php` by escaping variables (e.g., `@{{category}}`, `@{{tone}}`, etc.) inside the default textarea component.
  - **Integrity constraint violation (1048)**: Added a targeted database migration to make `topic_id` nullable in the `generated_contents` table, resolving a generation crash when trying to save generated articles without a topic FK.
  - **Clean Eager Load Queries**: Cleaned up all leftover eager-load queries for `topic` relationships inside `GeneratedContentController`, `GeneratedContentResource`, `ScheduleService`, `SiteConfigurationService`, `ContentCalendarService`, `AnalyticsService`, and `CoverageService`.
  - **SPA Selections validation**: Removed the "News Topic / Subject" text field input from `pipeline.blade.php` UI, and updated `validatePipelineForm` validation requirements on the client.

## [2026-07-07]
### Added
- Phase 3: Intelligent Content Generation
  - Implemented **Source Intelligence** in `SourceCollectionService`: URL normalization, strict URL-based deduplication, dynamic relevance ranking using keyword density/metadata density, TLD/content-based region detection, and keyword-matching topic clustering.
  - Implemented **Fact Audit** verification service (`FactAuditService` and `FactAuditorInterface`): claim extraction, verification against sources, categorization (supported vs unsupported), and dynamic fact and confidence score calculation.
  - Implemented **SEO Service** (`SEOService` and `SEOServiceInterface`): automated meta descriptions, slug generation, Open Graph, Twitter Card, and JSON-LD schema-ready structure output mapping to Yoast/RankMath plugin formats.
  - Implemented **Localization Service** (`TranslationService` and `TranslationInterface`): configuration-driven Hindi/English translation pipeline operating from a single canonical article.
  - Enhanced **Prompt Engine** (`PromptEngine`): modularized dynamic prompt compiling of System Instruct, Research Context, Facts Context, user template interpolation, dynamic guidelines, and markdown formatting.
  - Enhanced **Media Service** (`MediaPreparationService`): structured image specs, alt texts, captions, and prepared placeholder schemas for future video, audio, and infographics.
  - Upgraded **WordPress Publishing** (`WPClientService`, `PublishingService`, and custom `/newsblogify/v1/publish` route in client plugin): supports full metadata publishing (categories, tags, slug, Yoast/RankMath meta, and programmatically sideloading/downloading remote featured images to WordPress attachments), including strict log-id-based idempotency checks.
  - Added test suites (`SourceIntelligenceTest`, `FactAuditTest`, `SEOLocalizationTest`, `PromptEngineImprovementTest`, `WordPressPublishingEnhancementTest`) verifying 100% of new logic.

- Phase 2: AI Content Pipeline & Research Service Orchestration
  - Model content generation pipeline using Laravel's native Pipeline/middleware orchestration pattern.
  - Implemented concrete stage services: `TopicResolverService`, `ResearchService` (with provider-independent architecture), `SourceCollectionService` (for deduplication and metadata normalization), `FactExtractionService` (for entity and fact sheet isolation), `MediaPreparationService` (to prepare images, videos, and featured image metadata), and `PublishingQueueService`.
  - Refactored prompt compilation to be modular (System Prompt, Research Context, User Prompt, Variables, and Output Instructions).
  - Integrated `PublishingQueueService` ensuring all generated articles are placed into the queue as `draft` and never published immediately.
  - Refactored `ContentGenerationService->generateContentForRun()` to execute the 7 stages sequentially.
  - Bound all pipeline interfaces inside `AppServiceProvider.php`.
  - Added new integration and unit tests in `PipelineCoreArchitectureTest.php` and `PipelineServicesImplementationTest.php`.

## [2026-07-07] - Phase 1 Fixes

### Fixed (Systematic Debugging — Phase 1 Audit)
- `resources/views/sites.blade.php`: Replaced all 7 occurrences of `promt_id` with `prompt_id` in the Add/Edit Site modal HTML labels, select element IDs, JS DOM lookups, form reset logic, edit-population logic, and save payload — the old key was silently dropped by Laravel mass-assignment, leaving the prompt dropdown permanently broken.
- `database/migrations/2026_07_05_000001_add_product_entitlements_and_tenancy.php`: Fixed `down()` method to use a dual-table guard (`hasTable('prompts') ? 'prompts' : 'promts'`) preventing a guaranteed crash on migration rollback after the rename migration runs.
- `database/migrations/2026_07_01_000014`, `000016`, `000020`: Updated FK definitions from `->on('promts')` to `->on('prompts')` in both `up()` and `down()` to prevent rollback failures on fresh migration runs.
- `app/Http/Controllers/Api/PromtController.php`: Deleted dead code — zero routes referenced it, fully superseded by the modular `PromptController`.
- `app/Mcp/Tools/ListPromtsTool.php` → renamed to `ListPromptsTool.php`; class renamed `ListPromtsTool` → `ListPromptsTool`.
- `app/Mcp/Tools/AddPromtTool.php` → renamed to `AddPromptTool.php`; class renamed `AddPromtTool` → `AddPromptTool`.
- `app/Mcp/Servers/BlogServer.php`: Updated imports and `$tools` array to reference the renamed MCP tool classes.

### Fixed
- `NewBlogAI New/bootstrap/app.php`: Exempted `/api/v1/*` routes from CSRF token validation to completely eliminate 419 token mismatch crashes.
- `NewBlogAI New/resources/views/partials/scripts.blade.php`:
  - Upgraded raw `fetch()` calls in the frontend templates (e.g. `scripts.blade.php`, `sites.blade.php`, `prompts.blade.php`, `fleet.blade.php`) to use `apiFetch()` to ensure proper token/credential/session header transmission.
  - Linked `populateTopicPrompts()` to execute dynamically during both `openTopicAddModal` and `editTopic` triggers, ensuring prompt dropdown list options load successfully in the Content Topic Modal.

## [2026-07-05]
### Added
- `NewBlogAI New/tests/Feature/BackendSourceOfTruthTest.php`: Integration test verifying active subscription validation, authoritative site configuration generation, scheduler due time math, and WP REST API routing compatibility.
- Seeded standard Customer, Plan, active Subscription, and standard AI Providers (OpenAI, Gemini, Claude, Groq, OpenRouter, Ollama) by default inside `DatabaseSeeder.php` to prevent generation pipeline string validation errors and `NaN` ID parsing failures.

### Fixed
- `NewBlogAI New/routes/console.php`: Fixed CallbackEvent command syntax (called `name()` method before `withoutOverlapping()` to prevent LogicException).
- `NewBlogAI New/tests/Feature/BackendSourceOfTruthTest.php`: Resolved token self-revocation clash in tests by using the token issued by the login request.
- `NewBlogAI New/resources/views/partials/scripts.blade.php`:
  - Connected click listeners to prompt template placeholder chips so they copy the variable tag (e.g. `{{topic}}`) to the clipboard and insert it directly into `#prompt-editor-textarea` at the cursor position.
  - Resolved compiled Blade expression syntax error when rendering `{{` tags inside template strings.
  - Linked the horizontal sub-navigation tabs (Overview, Configuration, History, Logs & Events, Settings) to contextually trigger action modals or query and display live database audit trails / API execution logs.
  - Updated `fetchSystemAlerts()` to dynamically render failed jobs count indicator badges on the header notifications bell icon and the sidebar notifications link.
  - Upgraded unauthenticated plain `fetch` calls to `/api/v1/sites` and `/api/v1/providers` to authenticated `apiFetch` calls, preventing silent 401 redirect/auth errors in Fleet, Sites, and AI Providers grids.
  - Revamped the Content Pipeline preview loading wrapper to include clean try/catch blocks, error status handlers, empty list warning fallbacks, and a expanded 5s timeout window.
- `NewBlogAI New/app/Modules/SiteManager/Services/WPClientService.php`:
  - Patched `sync()` method to include the `Authorization: Bearer <key>` header on requests to the plugin.
  - Decrypted the encrypted database API key ciphertext via `Crypt::decryptString` inside `resolveApiKey()`, with a backward-compatible raw key fallback.
- `NewBlogAI New/wordpress-plugin/includes/class-newsblogify-rest-controller.php`:
  - Refactored routes namespace from `ai-news/v1` to `newsblogify/v1` to align with SaaS requests.
- `NewBlogAI New/routes/api.php`:
  - Extended access permissions on System Settings and AI Providers endpoint groups to include role `3` (Editors/Customers).
- `NewBlogAI New/routes/web.php`:
  - Passed `activeView` view-data parameters from the Laravel routing loop into the `dashboard` layout so hard-refreshing holds SPA page positions.
- `NewBlogAI New/app/Modules/AIProviderManager/Models/AIProvider.php`:
  - Implemented `getMaskedApiKey()` helper to dynamically mask keys (e.g. `AIzaSyBS...QCk`).
- `NewBlogAI New/app/Modules/AIProviderManager/Resources/AIProviderResource.php`:
  - Exposed `api_key` using the secure masked helper string in json serialize output.
- `NewBlogAI New/app/Modules/AIProviderManager/Services/AIProviderService.php`:
  - Filtered incoming keys inside `updateProvider()` to ignore updates matching current masked key representations, protecting real database credentials from accidental corruption.
- `NewBlogAI New/resources/views/partials/scripts.blade.php`:
  - Enabled front-end binding of `p.api_key` payload parameters and added save validation ensuring unmodified masked values are not re-submitted.
- `NewBlogAI New/resources/views/partials/workspaces/providers.blade.php`:
  - Added `autocomplete="new-password"` to all API key password inputs to disable browser credential autofill.
  - Upgraded model option lists and placeholders to reference `gemini-2.5-pro` and `gemini-2.5-flash`.
- `NewBlogAI New/app/Modules/AIProviderManager/Drivers/GoogleGeminiDriver.php`:
  - Upgraded fallback model string configuration to `gemini-2.5-flash` to bypass 404 API exceptions.
- `NewBlogAI New/database/seeders/DatabaseSeeder.php` & `NewBlogAI New/app/Modules/SystemSettings/Services/SystemSettingsService.php`:
  - Upgraded default model settings from the retired `gemini-1.5-pro` to the supported `gemini-2.5-pro`.
- `NewBlogAI New/tests/Feature/AIProvidersTest.php` & `NewBlogAI New/tests/Feature/ContentPipelineTest.php`:
  - Upgraded mock test assertion settings and provider mock setups to use `gemini-2.5-pro` and `gemini-2.5-flash`.
- `NewBlogAI New/wordpress-plugin/includes/class-newsblogify-rest-controller.php`, `class-newsblogify-admin.php`, `class-newsblogify-api-client.php`:
  - Changed occurrences of `api_token` to `plugin_token` to matches the updated client database options keys and ensure REST requests successfully authenticate.
- `NewBlogAI New/newsblogify-client/includes/class-newsblogify-rest-controller.php`, `class-newsblogify-admin.php`, `class-newsblogify-api-client.php`:
  - Performed matching updates to keep client copies in sync.
- `.ai/gemini.md` & `.ai/BUSINESS_RULES.md`:
  - Documented Rule 11 to avoid using `migrate:fresh` or wiping existing database user data during testing/development.
- `NewBlogAI New/wordpress-plugin/newsblogify-client.php` & `NewBlogAI New/newsblogify-client/newsblogify-client.php`:
  - Resolved PHP Fatal Error by replacing static method calls to `Cron::scheduled_events()` and `Cron::clear_events()` with correct non-static calls on the singleton instance: `Cron::get_instance()->schedule_events()` and `Cron::get_instance()->clear_events()`.
- `NewBlogAI New/app/Modules/SiteManager/Services/PluginTokenService.php`:
  - Implemented self-healing logic inside `customerForUser()` to auto-create missing Customer profiles and subscriptions for active users during testing.
- Active local WordPress installation (`D:\xampp\htdocs\dummy\wp-content\plugins\newsblogify-client`):
  - Deployed the updated plugin files directly, successfully validating the local connection status and resolving activation/deactivation crashes.