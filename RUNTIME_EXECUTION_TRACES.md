# RUNTIME EXECUTION PATH TRACES (NEWSBLOGIFY AI)

This document contains the end-to-end runtime execution path tracing for **NewsBlogify AI** as requested. It maps actual runtime routes, controllers, hooks, events, database schemas, and payloads between the Laravel monolith and the WordPress client plugin.

---

## 1. Which request starts the entire system?
Execution starts under two different contexts: **WordPress Plugin Bootstrap** and **Setup Wizard Account Connection**.

### A. Codebase/Plugin Initialization
1. **Plugin Bootstrap**: The WordPress plugin hooks its bootstrapping to the standard `plugins_loaded` hook:
   * **File**: [newsblogify-client.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/wordpress-plugin/newsblogify-client.php#L83)
   ```php
   add_action( 'plugins_loaded', 'run_newsblogify_client' );
   ```
2. **Component Registration**: `run_newsblogify_client()` (Lines 50–68) initializes the local logger, schedules background cron tasks, registers REST endpoints (`NewsBlogify\REST_Controller::register()`), and registers admin menu pages:
   * **File**: [class-newsblogify-admin.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/wordpress-plugin/includes/class-newsblogify-admin.php#L12)
   ```php
   add_action( 'admin_menu', [ $this, 'register_admin_pages' ] );
   ```

### B. Setup Wizard Onboarding (System Connection Start)
1. **Admin Dashboard Request**: The WordPress site administrator navigates to `/wp-admin/admin.php?page=newsblogify`.
2. **Wizard Rendering**: If the `newsblogify_wizard_step` database option is not set to `completed`, Step 1 of the Setup Wizard connection form is displayed ([class-newsblogify-admin.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/wordpress-plugin/includes/class-newsblogify-admin.php#L285)).
3. **Form Submission**: The admin inputs Laravel's `backend_url`, `email`, and `password`, and clicks "Connect & Authenticate".
   - This sends an HTTP POST request to the local WP admin dashboard URL with the parameter `newsblogify_action = wizard_step1`.
4. **Form Handler Hook**: Hooked to `admin_init` ([class-newsblogify-admin.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/wordpress-plugin/includes/class-newsblogify-admin.php#L13)), `Admin::handle_form_submissions()` processes this POST action:
   - **File**: [class-newsblogify-admin.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/wordpress-plugin/includes/class-newsblogify-admin.php#L105-L111)
   ```php
   if ( 'wizard_step1' === $action ) {
       ...
       $res = API_Client::get_instance()->connect_account( $backend_url, $email, $password );
   ```
5. **Initial External API Request**: This triggers the first API call to the Laravel monolith:
   - **File**: [class-newsblogify-api-client.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/wordpress-plugin/includes/class-newsblogify-api-client.php#L97)
   ```php
   $url = rtrim( $backend_url, '/' ) . '/api/plugin/login';
   ```

---

## 2. Which API endpoint does the WordPress plugin call first?
The first API endpoint called by the plugin is:
* **Endpoint**: `POST /api/plugin/login`
* **Mapped Controller**: `\App\Modules\SiteManager\Controllers\WPPluginAPIController@login`
* **Route Definition**: [api.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/routes/api.php#L121)
  ```php
  Route::post('login', [\App\Modules\SiteManager\Controllers\WPPluginAPIController::class, 'login']);
  ```
* **Plugin Invocation**: [class-newsblogify-api-client.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/wordpress-plugin/includes/class-newsblogify-api-client.php#L96-L128) inside `connect_account()`.

---

## 3. What authentication flow is used?

### A. WordPress-to-Laravel Authentication (Inbound to Laravel)
The WordPress plugin calls Laravel API routes protected by a custom Bearer token.

1. **Token Generation**:
   * During Setup Step 1, Laravel's `WPPluginAPIController::login()` checks credentials using Eloquent `User` model and `Hash::check()` ([WPPluginAPIController.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/app/Modules/SiteManager/Controllers/WPPluginAPIController.php#L53-L58)).
   * Upon authentication, Laravel generates a 60-character random token:
     `$token = Str::random(60);` (Line 61)
   * The token is stored in the database table `keys` with a named record format `'plugin-token-' . $user->id`:
     `keys::updateOrCreate(['name' => 'plugin-token-' . $user->id], ['key' => $token]);` (Lines 64–67).
   * Laravel returns the token as `'access_token' => $token`.
2. **Token Storage in WP**:
   * The plugin stores this key in its options:
     `Config::update( 'api_token', $res['access_token'] );` ([class-newsblogify-admin.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/wordpress-plugin/includes/class-newsblogify-admin.php#L119)).
3. **Request Signing**:
   * All subsequent plugin-to-Laravel API calls include the Bearer token in the request headers:
     `$headers['Authorization'] = 'Bearer ' . $api_token;` ([class-newsblogify-api-client.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/wordpress-plugin/includes/class-newsblogify-api-client.php#L51-L53)).
4. **Token Verification on Laravel**:
   * Incoming requests are processed by the helper method `authenticateToken(Request $request)` in `WPPluginAPIController`:
     * **File**: [WPPluginAPIController.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/app/Modules/SiteManager/Controllers/WPPluginAPIController.php#L23-L41)
     * Retrieves the Bearer token (`$request->bearerToken()`) or falls back to `api_key` parameter / `X-API-Key` header.
     * Looks up the token: `keys::where('key', $token)->first();`.
     * Validates that the record name prefix matches `'plugin-token-'`.
     * Extracts the User ID: `(int) str_replace('plugin-token-', '', $keyRecord->name)`.
     * Retrieves and returns the User: `User::find($userId)`.

### B. Laravel-to-WordPress Authentication (Inbound to WordPress)
Laravel calls WordPress REST API endpoints (to ping, sync-data, or publish posts).

1. **App Password Exchange**:
   * During Setup Step 2, the admin generates an **Application Password** in WordPress (Users -> Profile) and submits it as `wp_app_pwd`.
   * The plugin calls Laravel's `/api/plugin/register-website` passing the credentials.
   * Laravel registers the site and stores the plain WP Application Password in the `api_key` column of the `sites` database table ([WPPluginAPIController.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/app/Modules/SiteManager/Controllers/WPPluginAPIController.php#L115-L125)).
   * Concurrently, the WordPress plugin stores a SHA-256 hashed version of the Application Password in the WordPress options:
     `Config::update( 'wp_app_pwd', hash( 'sha256', $wp_app_pwd ) );` ([class-newsblogify-admin.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/wordpress-plugin/includes/class-newsblogify-admin.php#L157))
     It also stores the WordPress administrator's user ID: `Config::update( 'wp_user_id', get_current_user_id() );` (Line 158).
2. **Request Signing**:
   * Laravel's `WPClientService` resolves the plain key from the database: `$apiKey = $this->resolveApiKey($site);`.
   * Requests sent to WordPress include the header:
     `->withHeaders([ 'Authorization' => 'Bearer ' . $apiKey ])` ([WPClientService.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/app/Modules/SiteManager/Services/WPClientService.php#L118))
     or include the token directly as a request parameter `api_key` (Line 121).
3. **Request Verification on WordPress**:
   * The plugin registers a filter on `determine_current_user`:
     * **File**: [class-newsblogify-rest-controller.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/wordpress-plugin/includes/class-newsblogify-rest-controller.php#L13)
     ```php
     add_filter( 'determine_current_user', [ $instance, 'authenticate_bearer_token' ], 15 );
     ```
   * The callback `authenticate_bearer_token( $user_id )` (Lines 101–147) handles token verification:
     * Extracts the token from the `Authorization` header or query parameter `api_key`.
     * Fetches the stored hashed password (`$stored_app_pwd`), stored SaaS API token (`$stored_api_token`), and mapped admin user ID (`$mapped_user_id`).
     * Compares the SHA-256 hash of the incoming token to the stored credentials:
       `hash_equals( $stored_app_pwd, hash( 'sha256', $token ) )` (Line 139) or checks the SaaS API token directly `hash_equals( $stored_api_token, $token )` (Line 140).
     * If valid, returns `(int) $mapped_user_id`, authenticating the session.

---

## 4. What payloads are exchanged between the plugin and Laravel?
*(Excluding post publishing/sync payloads)*

### A. Account login (`connect_account`)
* **Endpoint**: `POST /api/plugin/login`
* **Request Payload**:
  ```json
  {
    "email": "admin@domain.com",
    "password": "saas_password"
  }
  ```
* **Response Payload**:
  ```json
  {
    "status": "success",
    "message": "Authentication successful.",
    "access_token": "60_char_token_string",
    "user": {
      "id": 1,
      "name": "Admin User",
      "email": "admin@domain.com"
    }
  }
  ```

### B. Website registration (`register_site`)
* **Endpoint**: `POST /api/plugin/register-website`
* **Headers**: `Authorization: Bearer <access_token>`
* **Request Payload**:
  ```json
  {
    "domain_url": "https://wordpress-site.local",
    "name": "My WordPress Site",
    "api_key": "xxxx xxxx xxxx xxxx xxxx xxxx", 
    "slot": "Daily"
  }
  ```
* **Response Payload**:
  ```json
  {
    "status": "success",
    "message": "Website registered successfully on backend.",
    "site_id": 3,
    "connection_status": "connected",
    "configuration": {
      "slot": "Daily",
      "selected_topics": ["General", "Tech", "SaaS", "AI"]
    }
  }
  ```

### C. Periodic Heartbeat (`send_heartbeat`)
* **Endpoint**: `POST /api/plugin/heartbeat`
* **Headers**: `Authorization: Bearer <access_token>`
* **Request Payload**:
  ```json
  {
    "plugin_version": "1.0.0",
    "site_url": "https://wordpress-site.local",
    "php_version": "8.2.10",
    "wp_version": "6.4.2"
  }
  ```
* **Response Payload**:
  ```json
  {
    "status": "success",
    "message": "Heartbeat logged successfully."
  }
  ```

---

## 5. How are topics synchronized?
Synchronization of topics and configuration options occurs in three flows:

### Flow A: Laravel-to-WordPress Push Sync (Manual/Job)
1. **Trigger**: Admin hits `POST /api/v1/sites/{id}/sync` which maps to `SiteController@sync` (in [SiteController.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/app/Modules/SiteManager/Controllers/SiteController.php#L101)).
2. **Laravel Queue Dispatch**: Dispatches the queue job `SyncSiteDataJob::dispatch($site)` to the `'site-sync'` queue.
3. **Job Execution**: `SyncSiteDataJob@handle` (in [SyncSiteDataJob.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/app/Modules/SiteManager/Jobs/SyncSiteDataJob.php#L51)) triggers `$clientService->sync($this->site)`.
4. **Laravel Request**: `WPClientService@sync` builds the payload containing `selected_topics`, `slot`, and `api_key` and dispatches it via a POST request to:
   `POST {domain}/wp-json/ai-news/v1/sync-data`
5. **WordPress Callback**: Hooked by `REST_Controller::register` (in [class-newsblogify-rest-controller.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/wordpress-plugin/includes/class-newsblogify-rest-controller.php#L19)). Processes setting serialization updates inside the local database `wp_options` under key `newsblogify_settings`.

### Flow B: WordPress-to-Laravel Pull Sync (WP Cron)
1. **Trigger**: Recurring WP Cron hook `newsblogify_sync_cron` triggers every 12 hours.
2. **WordPress Execution**: Binds to `Cron@run_configuration_sync` inside [class-newsblogify-cron.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/wordpress-plugin/includes/class-newsblogify-cron.php#L61).
3. **Laravel Request**: WordPress makes a GET request to:
   `GET {backend_url}/api/plugin/configuration?site_url={site_url}` with header `Authorization: Bearer <api_token>`.
4. **Laravel Handling**: Handled by `WPPluginAPIController@configuration` (in [WPPluginAPIController.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/app/Modules/SiteManager/Controllers/WPPluginAPIController.php#L142)). Updates database options locally in WordPress.

---

## 6. How does a scheduled job trigger AI content generation?
1. **System Scheduler**: The server's crontab executes Laravel's scheduler every minute via:
   `php artisan schedule:run`
2. **Console Routing**: The scheduler loads definitions from [console.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/routes/console.php).
3. **HTTP API Execute Command**: A request is sent to `POST /api/v1/pipelines/{id}/execute` (routed to `PipelineController@execute` in [PipelineController.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/app/Modules/ContentPipeline/Controllers/PipelineController.php#L83)), invoking `PipelineService->triggerRun()`.
4. **Database State & Queuing**: Inside a database transaction:
   - A pipeline execution run record is created in the `pipeline_runs` table with `status => 'queued'`.
   - The parent pipeline configuration in the `content_pipelines` table is updated to `status => 'queued'`.
   - `ProcessPipelineJob` is dispatched with the run ID: `ProcessPipelineJob::dispatch($run->id)`.

---

## 7. Which services, jobs, events, and queues execute next?
1. **Queue Job Start**: The queue worker (`php artisan queue:work`) picks up `ProcessPipelineJob` (run queue `'default'`).
2. **Queue Logs**: Event listeners in `AppServiceProvider@boot` capture queue events to write execution logs to the `job_logs` table.
3. **Service Orchestration**: `ProcessPipelineJob@handle` updates `pipeline_runs` status to `processing` and calls `ContentGenerationService->generateContentForRun()`.
4. **Prompt Compilation**: `ContentGenerationService` compiles variables (`topic`, `category`, `language`, `website`) and replaces tokens in template field `promt` within the `promts` table (spelled **`promt`**).
5. **Decryption of AI Keys**: Grabs `api_key` from model [AIProvider.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/app/Modules/AIProviderManager/Models/AIProvider.php). The property decrypts using Laravel's native encrypter based on the `APP_KEY` configuration.
6. **AI API Driver Call**: Resolves the appropriate AI driver class (e.g. `OpenAIDriver`) and executes `$client->generate()`, hit the external AI API, counts response token usage, calculates cost metrics, and logs metadata to `ai_request_logs`.

---

## 8. How is generated content stored?
1. **`generated_contents` table**: Creates a new record containing:
   - `pipeline_id`, `site_id`, `topic_id`.
   - `title` & `content` text.
   - `status` (set to `'draft'` by default for user review).
   - `metadata` (JSON field containing token usage details: `prompt_id`, `prompt_tokens`, `completion_tokens`, `total_tokens`, `cost`).
2. **`content_revisions` table**: Creates the initial content revision record with `user_id` set to `null` to indicate system-generated source content.
3. **`ai_request_logs` table**: Creates an API metrics audit log (tracks execution time, token count, cost, status).
4. **`pipeline_runs` table**: Updates execution status to `completed` and records `completed_at`.

---

## 9. How does PublishingService communicate with WordPress?
1. **Job Start**: When content is approved, `PublishingService` writes status record to `publishing_logs` table (status `'pending'`) and dispatches `PublishPostJob` to `'site-sync'` queue.
2. **Client Dispatch**: `PublishPostJob` invokes `PublishingService::executePublish()`, which delegates request to `WPClientService::publishPost()`.
3. **Outbound REST Request**: `WPClientService` resolves the plain WordPress application password credentials, disables SSL checking (`withoutVerifying()`), and makes an HTTP POST request to `POST {domain}/wp-json/wp/v2/posts` with header `Authorization: Bearer <app_password>`.

---

## 10. How does the plugin create or update WordPress posts?
1. **Authentication Interceptor**: The REST request is captured by WP Core. The plugin's filter handler `authenticate_bearer_token()` (hooked to `determine_current_user` in [class-newsblogify-rest-controller.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/wordpress-plugin/includes/class-newsblogify-rest-controller.php#L13)) extracts the Bearer token, compares its SHA-256 hash to option setting `wp_app_pwd`, and returns the administrative `wp_user_id` to establish standard user login privileges.
2. **Core Post Action**: WordPress REST API engine core routes the authenticated payload to the standard REST post controller (`WP_REST_Posts_Controller`).
3. **WP Insertion**: WordPress inserts a record into the `wp_posts` table and returns a JSON payload containing the new WP post ID (`id`) and link (`link`).
4. **Laravel State Update**: Laravel updates `PublishingLog` to `'completed'`, saves `wp_post_id` and `published_url`, and changes article status in `GeneratedContent` to `'published'`.

---

## 11. Where are retries, logging, and error handling performed?
* **Laravel Job Retries**: Queued jobs (`PublishPostJob` in [PublishPostJob.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/app/Modules/Publishing/Jobs/PublishPostJob.php)) specify `public $tries = 3;` and `public $backoff = 60;`. If an attempt fails, status updates to `'retrying'` and the job is released back to the queue. On the 3rd failure, status is set to `'failed'` and the article transitions back to `'draft'`.
* **Logging System**:
  - **Laravel backend**: standard events are written to Laravel logs.
  - **WordPress plugin**: standard operations are written via `Logger::log()` inside [class-newsblogify-logger.php](file:///D:/Company%20Work/Company%20projects/NewBlogAI/NewBlogAI%20New/wordpress-plugin/includes/class-newsblogify-logger.php). Logs are written to `wp-content/uploads/newsblogify-secure-logs/activity.log`. Access is denied to public queries using a custom generated `.htaccess` configuration.
* **Error Recovery (Status Sync)**: `PublishingService::syncPostStatus()` checks the post state via standard REST endpoints `GET /wp-json/wp/v2/posts/{id}`. If the query returns a `404 Not Found` (meaning the post was deleted or unpublished from WordPress), Laravel resets the article in `GeneratedContent` back to `'draft'` and marks `PublishingLog` as `'failed'`.

---

## 12. End-to-End Runtime Sequence Diagram

```mermaid
sequenceDiagram
    autonumber
    participant WP_Cron as WordPress Cron/Admin
    participant WP_Plugin as NewsBlogify Plugin
    participant WP_DB as WordPress Database
    participant Laravel_Route as Laravel Routing
    participant Laravel_Queue as Laravel Queue (Job/Worker)
    participant Laravel_DB as Laravel Database
    participant AI_Service as AI API (OpenAI/Anthropic/Gemini)

    %% Setup & Onboarding
    rect rgb(240, 240, 255)
    Note over WP_Cron, Laravel_DB: Phase 1: Authentication & Setup Wizard Onboarding
    WP_Cron->>WP_Plugin: Connect Account form submitted (wizard_step1)
    WP_Plugin->>Laravel_Route: POST /api/plugin/login {email, password}
    Laravel_Route->>Laravel_DB: Query user credentials
    Laravel_Route->>Laravel_DB: Generate Str::random(60) & save to keys table
    Laravel_Route-->>WP_Plugin: Response: access_token
    WP_Plugin->>WP_DB: Save access_token (newsblogify_settings)
    WP_Cron->>WP_Plugin: Register Website form submitted (wizard_step2)
    WP_Plugin->>Laravel_Route: POST /api/plugin/register-website {domain_url, wp_app_pwd, slot}
    Laravel_Route->>Laravel_DB: Save wp_app_pwd (api_key) in sites table
    WP_Plugin->>WP_DB: Save SHA-256 hashed wp_app_pwd & admin wp_user_id
    end

    %% Topic Sync & Telemetry
    rect rgb(240, 255, 240)
    Note over WP_Cron, Laravel_DB: Phase 2: Configuration Sync & Background Telemetry
    WP_Cron->>WP_Plugin: Hourly cron trigger (newsblogify_heartbeat_cron)
    WP_Plugin->>Laravel_Route: POST /api/plugin/heartbeat {plugin_version, php_version, wp_version}
    Laravel_Route->>Laravel_DB: Update last_synced_at & plugin_version in sites table
    WP_Cron->>WP_Plugin: 12-hour cron trigger (newsblogify_sync_cron)
    WP_Plugin->>Laravel_Route: GET /api/plugin/configuration?site_url={site}
    Laravel_Route-->>WP_Plugin: Response: site configurations
    WP_Plugin->>WP_DB: Update local selected_topics & posting_slot
    end

    %% AI Pipeline Content Generation
    rect rgb(255, 245, 235)
    Note over Laravel_Route, AI_Service: Phase 3: Scheduled AI Article Generation
    Laravel_Route->>Laravel_DB: Pipeline trigger execution run
    Laravel_Route->>Laravel_Queue: Dispatch ProcessPipelineJob (queued)
    Laravel_Queue->>Laravel_DB: Update run status to "processing"
    Laravel_Queue->>Laravel_Queue: Retrieve decrypted AIProvider api_key (casts decryption)
    Laravel_Queue->>Laravel_Queue: Compile prompt replacing template tokens
    Laravel_Queue->>AI_Service: HTTP POST completions request
    AI_Service-->>Laravel_Queue: Article body content + token usage metadata
    Laravel_Queue->>Laravel_DB: Save GeneratedContent ("draft") & content_revisions
    Laravel_Queue->>Laravel_DB: Log usage stats in ai_request_logs
    end

    %% Publication Handshake
    rect rgb(255, 240, 255)
    Note over Laravel_Route, WP_DB: Phase 4: Article Publishing & WP REST Verification
    Laravel_Route->>Laravel_DB: User approves article for publishing
    Laravel_Route->>Laravel_DB: Create PublishingLog (pending)
    Laravel_Route->>Laravel_Queue: Dispatch PublishPostJob
    Laravel_Queue->>WP_Plugin: HTTP POST /wp-json/wp/v2/posts (Bearer wp_app_pwd auth)
    WP_Plugin->>WP_Plugin: determine_current_user: verifies hash & log in as admin user
    WP_Plugin->>WP_DB: Write post record to wp_posts (status: publish)
    WP_Plugin-->>Laravel_Queue: Response JSON: wp_post_id, published_url
    Laravel_Queue->>Laravel_DB: Update PublishingLog (completed) & GeneratedContent (published)
    end
