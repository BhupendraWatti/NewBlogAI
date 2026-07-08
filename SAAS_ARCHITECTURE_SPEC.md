# SAAS ARCHITECTURE SPECIFICATION: NEWSBLOGIFY AI

This specification details the production-scale architecture of the **NewsBlogify AI** platform, reverse-engineered directly from the codebase. It traces structural components, dependencies, interfaces, schemas, state transitions, security, and deployments.

---

## 1. High-Level Component Architecture

NewsBlogify AI is structured around a **Modular Monolith** backend built on Laravel 11, communicating asynchronously with a client-side **WordPress Plugin** installed on user-owned blogs.

```mermaid
graph TB
    subgraph SaaS_Cloud[NewsBlogify SaaS Platform - Cloud Infrastructure]
        App[Laravel Monolith App - Web/Worker]
        Cache[Redis - Queue/Cache]
        DB[(MySQL Database)]
    end

    subgraph Client_Site[WordPress Client Site]
        WP_Core[WordPress Core]
        WP_Plugin[NewsBlogify Client Plugin]
        WP_DB[(WP Options & Posts DB)]
    end

    subgraph External_APIs[External AI Providers]
        OpenAI[OpenAI API]
        Anthropic[Anthropic API]
        Gemini[Google Gemini API]
        Groq[Groq API]
        Ollama[Ollama API]
    end

    %% Interactions
    WP_Plugin -- "POST /login & /register-website" --> App
    App -- "POST /sync-data & /posts (WP REST API)" --> WP_Plugin
    WP_Plugin -- "Cron: GET /configuration & POST /heartbeat" --> App

    App <--> DB
    App <--> Cache

    App -- "SDK/HTTP API Call (decrypted keys)" --> External_APIs
    WP_Plugin <--> WP_DB
```

---

## 2. Laravel Module Dependency Graph

The Laravel backend separates logic into modules inside `app/Modules/`. Each module is encapsulated with its own Controllers, Models, Services, Requests, and Jobs.

```mermaid
graph TD
    SiteManager[SiteManager]
    Publishing[Publishing]
    ContentPipeline[ContentPipeline]
    ContentGeneration[ContentGeneration]
    AIProviderManager[AIProviderManager]
    PromptManager[PromptManager]
    TopicManager[TopicManager]
    Licensing[Licensing]
    CustomerManager[CustomerManager]
    SubscriptionManager[SubscriptionManager]
    SystemSettings[SystemSettings]
    Operations[Operations]
    AuthManager[AuthManager]

    %% Dependencies
    SiteManager --> CustomerManager
    SiteManager --> Publishing
    
    ContentPipeline --> SiteManager
    ContentPipeline --> TopicManager
    ContentPipeline --> PromptManager
    ContentPipeline --> AIProviderManager
    ContentPipeline --> ContentGeneration

    ContentGeneration --> AIProviderManager
    ContentGeneration --> PromptManager
    ContentGeneration --> TopicManager
    ContentGeneration --> ContentPipeline

    Publishing --> SiteManager
    Publishing --> ContentGeneration

    Licensing --> CustomerManager
    SubscriptionManager --> CustomerManager
    CustomerManager --> SubscriptionManager
```

* **Integration Boundary Modules** (`SiteManager`, `Publishing`): Manage communication with remote WordPress endpoints.
* **Orchestration Layer Modules** (`ContentPipeline`, `ContentGeneration`): Execute multi-step operations (generation, compilation, parsing).
* **Library Modules** (`AIProviderManager`, `PromptManager`, `TopicManager`): Hold static definitions, encryption keys, and schema templates.
* **Core SaaS Modules** (`AuthManager`, `CustomerManager`, `SubscriptionManager`, `Licensing`): Handle tenant lifecycle, billing, and system states.

---

## 3. WordPress Plugin Internal Architecture

The plugin is structured in a procedural-to-object-oriented wrapper system to run securely on WordPress.

```
WordPress Plugin File Hierarchy
├── newsblogify-client.php          # Main entry bootstrap, hooks activation/deactivation
└── includes/
    ├── class-newsblogify-config.php  # Handles serialized option storage (newsblogify_settings)
    ├── class-newsblogify-logger.php  # Formats logs and handles secure .htaccess generation
    ├── class-newsblogify-api-client.php # Outgoing client wrapper using wp_remote_request
    ├── class-newsblogify-rest-controller.php # Registers REST endpoints and hooks Auth Interceptor
    └── class-newsblogify-cron.php    # Registers and processes hourly/daily scheduled cron hooks
```

```mermaid
classDiagram
    class Config {
        +get(string key, default)
        +update(string key, value)
        +delete(string key)
    }

    class Logger {
        -log_file_path
        +log(string level, string message)
        -ensure_secure_directory()
    }

    class API_Client {
        -api_token
        -backend_url
        +connect_account(url, email, password)
        +register_website(url, name, app_pwd)
        +send_heartbeat()
        -request(endpoint, method, body)
    }

    class REST_Controller {
        +register()
        +authenticate_bearer_token(user_id)
        +handle_sync(WP_REST_Request)
        +verify_api_key(WP_REST_Request)
    }

    class Cron {
        +schedule_events()
        +clear_events()
        +run_heartbeat()
        +run_configuration_sync()
    }

    API_Client --> Config
    REST_Controller --> Config
    REST_Controller --> Logger
    Cron --> API_Client
    Cron --> Config
```

---

## 4. Database ER Diagram

The database models leverage strict foreign key relations to map users, websites, AI logs, configurations, and licenses.

```mermaid
erDiagram
    users ||--o| keys : "owns"
    users ||--o{ audit_logs : "triggers"
    users ||--o{ content_revisions : "makes"
    
    customers ||--o{ sites : "owns"
    customers ||--o{ customer_notes : "has"
    customers ||--o{ subscriptions : "purchases"
    customers ||--o{ plugin_licenses : "possesses"
    
    sites ||--o{ content_pipelines : "configures"
    sites ||--o{ generated_contents : "stores"
    sites ||--o{ publishing_logs : "posts"
    sites ||--o{ plugin_licenses : "activates"
    
    topics ||--o{ content_pipelines : "feeds"
    promts ||--o{ content_pipelines : "structures"
    ai_providers ||--o{ content_pipelines : "processes"
    
    content_pipelines ||--o{ pipeline_runs : "records"
    content_pipelines ||--o{ generated_contents : "creates"
    
    generated_contents ||--o{ content_revisions : "tracks"
    generated_contents ||--o{ publishing_logs : "schedules"
    
    plans ||--o{ subscriptions : "assigns"
    subscriptions ||--o{ subscription_histories : "logs"
```

### Table Schema Definition Specifications

| Table | Primary Key | Foreign Key Columns | Description |
| :--- | :--- | :--- | :--- |
| **`users`** | `id` | - | SaaS operators, users, and administrators. |
| **`keys`** | `id` | - | Personal access tokens (named `plugin-token-{userId}`). |
| **`sites`** | `id` | `customer_id` | Remote WordPress website connections (API Key is encrypted). |
| **`topics`** | `id` | - | Scheduled categories and post concepts. |
| **`promts`** | `id` | - | Prompts template table (spelled **`promts`** with **`promt`** template column). |
| **`ai_providers`** | `id` | - | Decryption drivers for OpenAI, Anthropic, Gemini, Groq, etc. |
| **`content_pipelines`** | `id` | `site_id`, `topic_id`, `prompt_id`, `ai_provider_id` | Mapping configuration linking prompt templates to websites. |
| **`pipeline_runs`** | `id` | `pipeline_id` | Historical queue execution times and status runs. |
| **`generated_contents`** | `id` | `pipeline_id`, `site_id`, `topic_id` | AI output drafts and token metrics. |
| **`content_revisions`** | `id` | `generated_content_id`, `user_id` (nullable) | Version edit history tracking. |
| **`ai_request_logs`** | `id` | - | Detailed token tracking and cost auditing per API call. |
| **`publishing_logs`** | `id` | `generated_content_id`, `site_id`, `user_id` | Core WordPress REST publish queue results. |
| **`customers`** | `id` | - | Tenant accounts billing representation. |
| **`plugin_licenses`** | `id` | `customer_id`, `site_id` | Licensing activations (Inactive/Active/Expired/Revoked). |
| **`subscriptions`** | `id` | `customer_id`, `plan_id` | Subscription mapping per tier. |

---

## 5. API Contract Documentation

### A. WordPress Endpoints (Exposed by WP Plugin)

#### **1. Sync Data Settings**
* **Method & Path**: `POST /wp-json/ai-news/v1/sync-data`
* **Headers**: `Authorization: Bearer <wp_app_pwd>`
* **Request Schema**:
  ```json
  {
    "selected_topics": ["Tech", "SaaS", "AI"],
    "slot": "Daily",
    "api_key": "wp_app_password"
  }
  ```
* **Response (200 OK)**:
  ```json
  {
    "status": "success",
    "message": "WordPress settings sync completed successfully."
  }
  ```

#### **2. WordPress Core Post Creation**
* **Method & Path**: `POST /wp-json/wp/v2/posts/{wp_post_id?}`
* **Headers**: `Authorization: Bearer <wp_app_pwd>`
* **Request Schema**:
  ```json
  {
    "title": "Decentralized AI in 2026",
    "content": "Full article markup...",
    "status": "publish",
    "categories": [2],
    "date": "2026-07-05 03:00:00"
  }
  ```
* **Response (201 Created)**:
  ```json
  {
    "id": 1024,
    "link": "https://clientblog.com/decentralized-ai-2026/",
    "status": "publish"
  }
  ```

---

### B. Laravel Backend Endpoints (Exposed to WP Plugin)

#### **1. Account Login**
* **Method & Path**: `POST /api/plugin/login`
* **Request Schema**:
  ```json
  {
    "email": "saas_user@gmail.com",
    "password": "password"
  }
  ```
* **Response (200 OK)**:
  ```json
  {
    "status": "success",
    "access_token": "random_60_char_token_string",
    "user": { "id": 1, "name": "Manager User" }
  }
  ```

#### **2. Site Registration**
* **Method & Path**: `POST /api/plugin/register-website`
* **Headers**: `Authorization: Bearer <access_token>`
* **Request Schema**:
  ```json
  {
    "domain_url": "https://clientblog.com",
    "name": "My Blog",
    "api_key": "xxxx xxxx xxxx xxxx",
    "slot": "Daily"
  }
  ```
* **Response (200 OK)**:
  ```json
  {
    "status": "success",
    "site_id": 3,
    "configuration": { "slot": "Daily", "selected_topics": [] }
  }
  ```

---

### C. Workspace & Employee Endpoints (Enterprise API)

#### **1. List Workspaces**
* **Method & Path**: `GET /api/v1/workspaces`
* **Headers**: `Authorization: Bearer <access_token>`
* **Parameters**: `customer_id` (optional, SuperAdmin only), `limit` (optional, default 15)
* **Response (200 OK)**:
  ```json
  {
    "data": [
      {
        "id": 1,
        "name": "Production Workspace",
        "customer_id": "uuid-string-here",
        "sites": [],
        "employees": [],
        "created_at": "2026-07-09T03:00:00.000000Z",
        "updated_at": "2026-07-09T03:00:00.000000Z"
      }
    ],
    "links": {},
    "meta": {}
  }
  ```

#### **2. Create Workspace**
* **Method & Path**: `POST /api/v1/workspaces`
* **Headers**: `Authorization: Bearer <access_token>`
* **Request Schema**:
  ```json
  {
    "name": "New Team Workspace",
    "customer_id": "uuid-string-here"
  }
  ```
* **Response (201 Created)**:
  ```json
  {
    "data": {
      "id": 2,
      "name": "New Team Workspace",
      "customer_id": "uuid-string-here",
      "sites": [],
      "employees": [
        {
          "id": 5,
          "workspace_id": 2,
          "user_id": 12,
          "role": "Owner"
        }
      ]
    }
  }
  ```

#### **3. List Workspace Employees**
* **Method & Path**: `GET /api/v1/workspaces/{id}/employees`
* **Headers**: `Authorization: Bearer <access_token>`
* **Response (200 OK)**:
  ```json
  {
    "data": [
      {
        "id": 5,
        "workspace_id": 2,
        "user_id": 12,
        "role": "Owner",
        "user": {
          "id": 12,
          "name": "Jane Doe",
          "email": "jane@company.com"
        }
      }
    ]
  }
  ```

#### **4. Add Workspace Employee**
* **Method & Path**: `POST /api/v1/workspaces/{id}/employees`
* **Headers**: `Authorization: Bearer <access_token>`
* **Request Schema**:
  ```json
  {
    "user_id": 15,
    "role": "Editor"
  }
  ```
* **Response (201 Created)**:
  ```json
  {
    "data": {
      "id": 6,
      "workspace_id": 2,
      "user_id": 15,
      "role": "Editor"
    }
  }
  ```

#### **5. Update Employee Role**
* **Method & Path**: `PUT /api/v1/workspaces/{id}/employees/{employeeId}`
* **Headers**: `Authorization: Bearer <access_token>`
* **Request Schema**:
  ```json
  {
    "role": "Admin"
  }
  ```
* **Response (200 OK)**:
  ```json
  {
    "data": {
      "id": 6,
      "workspace_id": 2,
      "user_id": 15,
      "role": "Admin"
    }
  }
  ```

#### **6. Remove Employee**
* **Method & Path**: `DELETE /api/v1/workspaces/{id}/employees/{employeeId}`
* **Headers**: `Authorization: Bearer <access_token>`
* **Response (200 OK)**:
  ```json
  {
    "message": "Employee removed from workspace."
  }
  ```

---

## 6. Queue & Job Lifecycle Diagram

The queue structure handles heavy processing tasks (AI completions, HTTP publication) outside of the HTTP request lifecycle.

```mermaid
stateDiagram-v2
    [*] --> Dispatched : Job dispatched to Queue
    
    state Dispatched {
        [*] --> Queue_Log : AppServiceProvider Queue::before Event
        Queue_Log --> Processing : Job Log set to 'processing'
    }
    
    Processing --> Running : Worker executes handle()
    
    state Running {
        [*] --> API_Request : Call External API (AI/WordPress)
        
        API_Request --> HTTP_Success : Response 200 OK / 201 Created
        HTTP_Success --> DB_Commit : Commit DB changes inside transaction
        
        API_Request --> HTTP_Fail : Request fails / Timeout
        HTTP_Fail --> Raise_Exception : Exception thrown
    }
    
    DB_Commit --> Completed : Job logs completed status
    Completed --> [*] : Job removed from Queue

    Raise_Exception --> Failed : AppServiceProvider Queue::failing Event
    
    state Failed {
        [*] --> Retry_Check : Attempts < tries (3)?
        Retry_Check --> Requeued : Yes (Release job with backoff delay)
        Retry_Check --> Maxed_Out : No (Mark Job failed)
    }
    
    Requeued --> Dispatched : Return to Queue
    Maxed_Out --> Reset_Draft : Reset local model state to 'draft'
    Reset_Draft --> [*]
```

---

## 7. Authentication & Security Architecture

```mermaid
graph LD
    subgraph WP_Security[WordPress Security Layers]
        Auth_Filter[determine_current_user filter hook]
        Hash_Verify[hash_equals verification]
        Log_Lock[Secure Log Directory]
        Htaccess[Deny from all .htaccess]
    end

    subgraph Laravel_Security[Laravel Security Layers]
        Key_Decryption[Eloquent Key Decrypt casts]
        Bearer_Lookup[keys table lookup]
        App_Key[Laravel APP_KEY Encryption]
    end

    %% Operations
    Auth_Filter --> Hash_Verify
    Log_Lock --> Htaccess
    Key_Decryption --> App_Key
```

* **Encryption-at-Rest**: SaaS credentials (WordPress Application Passwords and AI API tokens) are encrypted in the database using Laravel's native encrypter. Models like `Site` and `AIProvider` enforce the `casts` system:
  ```php
  'api_key' => 'encrypted'
  ```
  This encrypts values before database insertion (AES-256-CBC) using the environment's `APP_KEY`.
* **WP Log Protection**: The WordPress plugin restricts access to logs by writing an `.htaccess` block on directory creation:
  ```apache
  Order deny,allow
  Deny from all
  ```
  An empty `index.php` is placed inside the directory to prevent folder index list lookups.

---

## 8. Content Pipeline Architecture

```mermaid
graph LR
    Category[Categories & Topics] --> Context[Variables Compilation]
    Prompt[Prompt Template] --> Context
    
    Context --> Cast[Eloquent Key Decryption]
    Cast --> Adapter[AI Provider Driver Adapter]
    
    Adapter --> Outbound[Outbound API Request]
    Outbound --> Metrics[Token Usage & Cost metrics logger]
```

1. **Category Feed**: Topics and languages are fed into `ContentGenerationService`.
2. **Context Compilation**: Placeholders (`{{topic}}`, `{{category}}`, `{{language}}`, `{{website}}`) are compiled inside the template field `promt` (from the `promts` database table).
3. **Decryption**: Active AI Provider access tokens are decrypted.
4. **Adapter Factory**: Maps keys (`openai`, `anthropic`, etc.) to custom API drivers.
5. **Metrics Logging**: Logs latency, counts prompt/completion/total tokens, translates them into USD billing costs, and updates `ai_request_logs`.

---

## 9. State Machines

### A. ContentPipeline
Maps automated trigger configurations.

```mermaid
stateDiagram-v2
    [*] --> Inactive : Created
    Inactive --> Active : Admin toggles activation
    Active --> Inactive : Admin toggles deactivation
    
    state Active {
        [*] --> Idle
        Idle --> Queued : Scheduled cron or manual execution runs
        Queued --> Idle : Queue dispatcher completes
    }
```

### B. PipelineRun
Logs lifecycle of each background queue generation step.

| Current State | Event | Target State | DB Column Action |
| :--- | :--- | :--- | :--- |
| **`queued`** | Queue Job Starts | **`processing`** | Sets `started_at = now()`, `status = 'processing'` |
| **`processing`** | AI Response Success | **`completed`** | Sets `completed_at = now()`, `status = 'completed'` |
| **`processing`** | API Error / Timeout | **`failed`** | Increments `retry_count`, logs `error_message`, status set to `'failed'` |
| **`failed`** | Manual Retry | **`queued`** | Resets `error_message`, sets `status = 'queued'` |

### C. GeneratedContent
Represents the status lifecycle of generated articles.

```mermaid
stateDiagram-v2
    [*] --> Draft : Generated by ContentGenerationService
    Draft --> Pending_Review : PublishingService queuePublish() triggered
    
    Pending_Review --> Published : WordPress REST post response 201 Success
    Pending_Review --> Draft : PublishPostJob failed/maxed out retry limit
    
    Published --> Draft : Status Sync detects deleted post on WordPress (404)
```

---

## 10. Failure Recovery & Retry Architecture

### A. HTTP Failures & Timeouts
* **Issue**: Slow remote WP responses or connection drops.
* **Resolution**: Laravel's `WPClientService` executes HTTP requests with a `200` second timeout, protecting worker processes from hanging.

### B. Queue Retries and Job Release
* **Strategy**: `PublishPostJob` has a maximum limit of **3 attempts** with a **60 second backoff delay**.
* **Path**:
  - `PublishPostJob` catches exceptions during execution.
  - If attempt count is $< 3$, status is set to `'retrying'`, the current attempt number is saved in `retry_count`, and the job is released back to the queue: `$this->release($this->backoff)`.
  - If attempts $\ge 3$, status changes to `'failed'`, the error log is saved in `publishing_logs`, and the content status transitions back to `'draft'` to allow manual corrections.

### C. Orphaned Post Validation (Status Sync)
* **Issue**: A post is deleted or unpublished directly in WordPress by a user, causing Laravel records to become out of sync.
* **Resolution**: Laravel checks post existence via `GET /wp-json/wp/v2/posts/{wp_post_id}`. If the REST API returns HTTP `404 Not Found`, the backend marks the local publication log as `'failed'` (setting the error message to `"Post was deleted or unpublished from WordPress"`) and resets the article status back to `'draft'` to allow republishing.

---

## 11. Deployment Architecture

```mermaid
graph TB
    subgraph Production_Cloud[Production Scale SaaS - AWS Cloud Cluster]
        CF[Cloudflare WAF / CDN] --> ALB[AWS Application Load Balancer]
        
        subgraph ECS_Cluster[AWS ECS Fargate Container Group]
            Web_App[Laravel Web App Containers]
            Worker_App[Laravel Queue Worker Containers]
        end
        
        ALB --> Web_App
        
        Redis[(AWS ElastiCache Redis)] <--> Web_App
        Redis <--> Worker_App
        
        RDS[(AWS RDS Aurora MySQL)] <--> Web_App
        RDS <--> Worker_App
        
        S3[(AWS S3 Storage)] <--> Web_App
    end

    subgraph Dev_Environment[Local Development Stack]
        XAMPP[XAMPP Server]
        PHP_Server[PHP Local Dev Server - localhost:8000]
        Local_DB[(Local MySQL DB)]
    end
```

* **Production Stack**:
  - **Reverse Proxy**: Cloudflare handles SSL offloading, DDoS protection, and routes queries.
  - **Compute Containers**: AWS ECS Fargate runs isolated containers for the Laravel web server and queue workers.
  - **Shared Storage**: AWS S3 acts as a filesystem to store generated media files.
  - **Cache & Queue Database**: AWS ElastiCache Redis stores sessions, handles cache keys, and drives queue operations.
  - **SQL Database**: Aurora MySQL Multi-AZ handles transaction schemas.

---

## 12. Configuration Flow

Configurations are synchronized via push and pull actions to keep both databases in sync.

```mermaid
graph TD
    subgraph Laravel_DB[Laravel DB]
        L_Config[selected_topics & posting_slots]
    end

    subgraph WP_DB[WordPress DB]
        WP_Config[selected_topics & posting_slot]
    end

    %% Flows
    L_Config -- "1. Manual Push Sync / SyncSiteDataJob" --> WP_Config
    WP_Config -- "2. Twice-Daily Cron Configuration Pull" --> L_Config
    WP_Config -- "3. Admin Force Sync Request" --> L_Config
```

1. **Manual Push Sync**: Changes made to settings in the Laravel dashboard are pushed to the WordPress plugin database using `SyncSiteDataJob`.
2. **Cron Pull Sync**: The WordPress plugin's recurring cron job queries Laravel twice daily to pull the latest configuration settings.
3. **WordPress Manual Sync**: An administrator forces a sync from the WordPress admin interface, pulling settings directly from the Laravel backend.

---

## 13. Sequence Diagrams for Core Features

### A. Setup Wizard Onboarding Sequence

```mermaid
sequenceDiagram
    autonumber
    actor Admin
    participant WP_Admin as WP Admin Panel
    participant WP_Plugin as NewsBlogify Plugin
    participant SaaS_API as Laravel Backend API
    participant SaaS_DB as Laravel DB

    Admin->>WP_Admin: Navigate to NewsBlogify dashboard
    Note over WP_Admin: Detect wizard is uncompleted
    WP_Admin-->>Admin: Render Setup Wizard Step 1

    Admin->>WP_Admin: Enters Email, Password, & SaaS Backend URL
    WP_Admin->>WP_Plugin: Trigger connect_account()
    WP_Plugin->>SaaS_API: POST /api/plugin/login {email, password}
    SaaS_API->>SaaS_DB: Query credentials & generate Bearer Token
    SaaS_API-->>WP_Plugin: HTTP 200 {access_token, user_details}
    WP_Plugin->>WP_Admin: Save api_token inside settings
    WP_Admin-->>Admin: Render Setup Wizard Step 2

    Admin->>WP_Admin: Generates WP Application Password & inputs to wizard
    WP_Admin->>WP_Plugin: Trigger register_website()
    WP_Plugin->>SaaS_API: POST /api/plugin/register-website {domain_url, wp_app_pwd, slot}
    SaaS_API->>SaaS_DB: Save encrypted App Password (api_key) in sites table
    SaaS_API-->>WP_Plugin: HTTP 200 {site_id, configuration}
    WP_Plugin->>WP_Admin: Save hashed wp_app_pwd & admin wp_user_id
    WP_Admin-->>Admin: Render Onboarding Completion Dashboard
```

### B. Configuration Synchronization Sequence

```mermaid
sequenceDiagram
    autonumber
    participant WP_Cron as WP Cron System
    participant WP_Plugin as NewsBlogify Plugin
    participant WP_DB as WP Options DB
    participant Laravel as Laravel Backend (API Controller)

    %% Twice Daily Pull Cron
    Note over WP_Cron, Laravel: Twice-Daily Configuration Pull Sync
    WP_Cron->>WP_Plugin: Trigger newsblogify_sync_cron
    WP_Plugin->>Laravel: GET /api/plugin/configuration?site_url={site} (Bearer SaaS token)
    Laravel-->>WP_Plugin: Return site config JSON (slot, selected_topics)
    WP_Plugin->>WP_DB: Write options newsblogify_settings (update category keys)
    WP_Plugin-->>WP_Cron: Done

    %% Manual Sync Push
    Note over WP_Cron, Laravel: Laravel Manual Sync Push Sync
    actor Admin
    Admin->>Laravel: Trigger Manual sync on Laravel dashboard
    Laravel->>Laravel: Dispatch SyncSiteDataJob
    Laravel->>WP_Plugin: POST /wp-json/ai-news/v1/sync-data (Bearer WP app password)
    WP_Plugin->>WP_DB: Update settings options (selected_topics, posting_slot)
    WP_Plugin-->>Laravel: Return 200 OK success
```

### C. Periodic Heartbeat Sync Sequence

```mermaid
sequenceDiagram
    autonumber
    participant WP_Cron as WP Cron Daemon
    participant WP_Plugin as NewsBlogify Plugin
    participant Laravel_API as Laravel API Controller
    participant Laravel_DB as Laravel DB

    WP_Cron->>WP_Plugin: Trigger hourly newsblogify_heartbeat_cron
    WP_Plugin->>WP_Plugin: Extract PHP & WP versions, URL, & plugin version
    WP_Plugin->>Laravel_API: POST /api/plugin/heartbeat {plugin_version, php_version, wp_version, site_url}
    Laravel_API->>Laravel_DB: Authenticate token in keys table
    Laravel_API->>Laravel_DB: Find Site domain & update last_synced_at & version strings
    Laravel_API-->>WP_Plugin: Return HTTP 200 success
    WP_Plugin->>WP_Plugin: Log periodic heartbeat success to activity.log
```

### D. AI Generation Run Sequence

```mermaid
sequenceDiagram
    autonumber
    participant Scheduler as Artisan Console Scheduler
    participant Laravel_Queue as Queue Worker
    participant Laravel_DB as Laravel DB
    participant AI_Service as External AI Provider API

    Scheduler->>Laravel_Queue: Trigger Pipeline Execution Command
    Laravel_Queue->>Laravel_DB: Create PipelineRun (queued)
    Laravel_Queue->>Laravel_DB: Set status to "processing", fetch prompt/topic details
    Laravel_Queue->>Laravel_DB: Decrypt AIProvider api_key using casts decryption
    Laravel_Queue->>Laravel_Queue: Compile template (replace topic & categories placeholders)
    Laravel_Queue->>AI_Service: POST Chat Completions Request
    AI_Service-->>Laravel_Queue: Returns generated content text & token usage statistics
    Laravel_Queue->>Laravel_DB: Insert GeneratedContent ("draft") & content_revisions
    Laravel_Queue->>Laravel_DB: Insert usage log in ai_request_logs
    Laravel_Queue->>Laravel_DB: Set PipelineRun status to "completed"
```

### E. Article Publishing Sequence

```mermaid
sequenceDiagram
    autonumber
    actor Publisher
    participant Laravel as Laravel Backend
    participant Laravel_Queue as Queue Worker
    participant WP_Plugin as WordPress Plugin
    participant WP_DB as WordPress DB

    Publisher->>Laravel: Clicks "Publish" on approved GeneratedContent draft
    Laravel->>Laravel: Create PublishingLog (pending) & dispatch PublishPostJob
    Laravel-->>Publisher: Display publishing progress status
    
    Laravel_Queue->>Laravel_Queue: Fetch Job log & set status "processing"
    Laravel_Queue->>WP_Plugin: POST /wp-json/wp/v2/posts (Bearer WP App Password)
    WP_Plugin->>WP_Plugin: Authenticate App Password, load admin user privileges
    WP_Plugin->>WP_DB: Create post inside wp_posts table (status: publish)
    WP_Plugin-->>Laravel_Queue: Return HTTP 201 {id, link}
    
    Laravel_Queue->>Laravel_DB: Update PublishingLog (completed, wp_post_id)
    Laravel_Queue->>Laravel_DB: Update GeneratedContent status to "published"
```

---

## 14. C4 Model (Context, Container, Component, Code)

### Level 1: System Context Diagram
Shows the high-level boundaries of the system and how users interact with it.

```mermaid
graph TB
    User((Content Editor / SaaS Admin)) --> SaaS[NewsBlogify AI Platform]
    SaaS --> WP[WordPress Blog Sites]
    SaaS --> AI[AI APIs]
```

---

### Level 2: Container Diagram
Details the architectural containers (SaaS Web App, Queue Workers, Databases, and the WP plugin).

```mermaid
graph TB
    actor Admin[SaaS Editor / Admin]
    
    subgraph SaaS_Containers[SaaS Platform Containers]
        Web_App[Laravel Web App - PHP/Nginx]
        Queue_Worker[Laravel Queue Workers]
        Redis[Redis Cache / Queue]
        MySQL[(MySQL Database)]
    end

    subgraph Client_Containers[WordPress Containers]
        WP_App[WordPress Instance - Apache/PHP]
        WP_DB[(WordPress Options/Posts DB)]
    end

    subgraph External_Containers[External APIs]
        AI_APIs[AI Providers - OpenAI / Anthropic / Google]
    end

    %% Interactions
    Admin --> Web_App
    
    Web_App <--> Redis
    Queue_Worker <--> Redis
    
    Web_App <--> MySQL
    Queue_Worker <--> MySQL
    
    Web_App -- "1. Connection handshakes & syncs" --> WP_App
    Queue_Worker -- "2. Publications / Sync-Data POSTs" --> WP_App
    WP_App -- "3. Heartbeat crons & configurations" --> Web_App

    WP_App <--> WP_DB
    
    Queue_Worker -- "4. AI generation calls" --> AI_APIs
```

---

### Level 3: Component Diagram (Focus: Core Integration Modules)
Breaks down the components inside the Laravel Container that handle communication with WordPress.

```mermaid
graph TB
    subgraph Web_App_Container[Laravel Container Components]
        API_Route[API Router] --> WPPluginAPI[WPPluginAPIController]
        Web_Route[Web Router] --> SiteController[SiteController]
        Web_Route --> PublishingController[PublishingController]
        
        WPPluginAPI --> SiteService[SiteService]
        SiteController --> SiteService
        
        PublishingController --> PublishingService[PublishingService]
        
        SiteService --> WPClient[WPClientService]
        PublishingService --> WPClient
    end

    subgraph Worker_App_Container[Queue Container Components]
        Queue_Dispatch[Queue Dispatcher] --> SyncJob[SyncSiteDataJob]
        Queue_Dispatch --> PublishJob[PublishPostJob]
        
        SyncJob --> WPClient
        PublishJob --> PublishingService
    end

    subgraph WP_Container[WordPress Container Components]
        WP_REST[WP Core REST API Engine] --> REST_Ctrl[NewsBlogify REST Controller]
        WP_Cron[WP-Cron System] --> Cron_Class[NewsBlogify Cron Handler]
    end

    %% Outbound connections
    WPClient -- "HTTP Requests" --> WP_REST
    Cron_Class -- "HTTP Requests" --> API_Route
```

---

### Level 4: Code Diagram (Focus: Site Connections & REST Verification)
Maps the exact class-level relationships and call methods that handle authentication and sync actions.

```mermaid
classDiagram
    class WPPluginAPIController {
        +login(Request) JsonResponse
        +registerWebsite(Request) JsonResponse
        +heartbeat(Request) JsonResponse
        +configuration(Request) JsonResponse
        -authenticateToken(Request) User
    }

    class WPClientService {
        +validateConnection(Site) bool
        +sync(Site) bool
        +publishPost(Site, title, content, status, scheduled_at, wp_post_id) array
        -resolveApiKey(Site) string
    }

    class NewsBlogify_REST_Controller {
        +register() void
        +authenticate_bearer_token(user_id) int
        +handle_sync(WP_REST_Request) WP_REST_Response
        -verify_api_key(WP_REST_Request) bool
    }

    class NewsBlogify_API_Client {
        +connect_account(url, email, password) array
        +register_website(url, name, app_pwd) array
        +send_heartbeat() array
        -request(endpoint, method, payload) array
    }

    WPPluginAPIController --> WPClientService : "calls sync"
    NewsBlogify_API_Client -- "POST /login & /register-website" --> WPPluginAPIController
    WPClientService -- "POST /sync-data & /posts" --> NewsBlogify_REST_Controller
```
