# MODULE OWNERSHIP

Laravel is the platform's single source of truth. WordPress is an execution adapter that authenticates, pulls configuration, sends health telemetry, and creates or updates posts from backend-issued work.

| Product capability | Owning module | Authoritative data and rules |
| --- | --- | --- |
| Customers, Workspaces & Teams | CustomerManager | Tenant identity, lifecycle, workspaces, employee memberships, team boundaries, and website relationship |
| Subscription plans and limits | SubscriptionManager | Plan definitions, subscription lifecycle, entitlement snapshots, usage limits, provider availability, and feature flags |
| Feature restrictions | SubscriptionManager | The `EntitlementService` interface is the only place that interprets and enforces plan limits |
| Website registration and management | SiteManager | Customer ownership, connection credentials, activation state, final plugin configuration, and synchronization |
| Topics | TopicManager | Subscription-owned topic taxonomy, language, priority, status, and generation frequency |
| Prompts | PromptManager | Topic-owned prompt templates, variables, versions, and status |
| AI providers & Credits | AIProviderManager | Provider credentials, masked API keys, enabled status, dynamic credits tracking, credit refresh logic, and connection checks |
| Content pipelines | ContentPipeline | Valid site-category-prompt-provider composition, execution concurrency, and execution-run lifecycle |
| Scheduling | ScheduleManager | Website schedules, frequency, timezone, due-run calculation, and delegation to pipelines |
| Generated content | ContentGeneration | Prompt compilation, chronological event analysis, AI execution, drafts, revisions, request usage, failover retry loops, and content state |
| Publishing queue | Publishing | Queue state, WordPress post commands, retry/cancel behavior, and publication results |
| Analytics, Notifications & Webhooks | Operations | Aggregation, audit logs, queue logs, schedule logs, health reporting, in-app notifications feeds, and Slack/Discord/Generic webhook channels |
| Licensing | Licensing | License lifecycle and domain activation; installation capacity is constrained by SubscriptionManager |
| User permissions | AuthManager | Authentication, roles, policies, and authorization activity |
| API configuration | SystemSettings | Backend operational settings, image generation flags, and cached configuration values |

## Allowed interactions

- Product modules ask SubscriptionManager whether an operation is allowed; they do not inspect plan columns directly.
- SubscriptionManager manages invoices, transactions, and coupon redemptions, providing secure tenant-isolated ledgers.
- SiteManager composes plugin configuration by reading owning modules. Other modules do not construct plugin payloads.
- ScheduleManager decides when work is due and delegates generation to ContentPipeline.
- ContentPipeline and ContentGeneration capture and propagate the user_id to all runs, generated drafts, and request logs for user/employee attribution.
- ContentGeneration uses AIProviderManager, handles provider failover retries, and records attributable usage.
- Publishing delegates remote WordPress transport to SiteManager's WordPress client.
- Operations reads other modules for reporting and dispatches notifications, but does not mutate their core business state.
- Licensing may request installation entitlements from SubscriptionManager but does not define subscription limits.
- CustomerManager isolates workspaces and employee resources per tenant (Customer ID). Only users belonging to the customer can be added as team members.

## WordPress responsibility

The plugin may cache the latest configuration for resilience, but cached values are never authoritative. It must not choose topics, prompts, providers, schedules, limits, feature availability, or publishing policy. Those decisions are emitted by Laravel as a versioned site configuration.
