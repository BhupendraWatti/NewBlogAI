# MODULE OWNERSHIP

Laravel is the platform's single source of truth. WordPress is an execution adapter that authenticates, pulls configuration, sends health telemetry, and creates or updates posts from backend-issued work.

| Product capability | Owning module | Authoritative data and rules |
| --- | --- | --- |
| Customers | CustomerManager | Tenant identity, lifecycle, notes, activity, and website relationship |
| Subscription plans and limits | SubscriptionManager | Plan definitions, subscription lifecycle, entitlement snapshots, usage limits, provider availability, and feature flags |
| Feature restrictions | SubscriptionManager | The `EntitlementService` interface is the only place that interprets and enforces plan limits |
| Website registration and management | SiteManager | Customer ownership, connection credentials, activation state, final plugin configuration, and synchronization |
| Topics | TopicManager | Subscription-owned topic taxonomy, language, priority, status, and generation frequency |
| Prompts | PromptManager | Topic-owned prompt templates, variables, versions, and status |
| AI providers | AIProviderManager | Provider credentials, enabled state, default model, drivers, and connection checks |
| Content pipelines | ContentPipeline | Valid site-topic-prompt-provider composition and execution-run lifecycle |
| Scheduling | ScheduleManager | Website schedules, frequency, timezone, due-run calculation, and delegation to pipelines |
| Generated content | ContentGeneration | Prompt compilation, AI execution, drafts, revisions, request usage, and content state |
| Publishing queue | Publishing | Queue state, WordPress post commands, retry/cancel behavior, and publication results |
| Analytics and operational telemetry | Operations | Aggregation, audit logs, queue logs, schedule logs, and health reporting |
| Licensing | Licensing | License lifecycle and domain activation; installation capacity is constrained by SubscriptionManager |
| User permissions | AuthManager | Authentication, roles, policies, and authorization activity |
| API configuration | SystemSettings | Backend operational settings and cached configuration values |

## Allowed interactions

- Product modules ask SubscriptionManager whether an operation is allowed; they do not inspect plan columns directly.
- SiteManager composes plugin configuration by reading owning modules. Other modules do not construct plugin payloads.
- ScheduleManager decides when work is due and delegates generation to ContentPipeline.
- ContentPipeline validates composition and delegates AI work to ContentGeneration.
- ContentGeneration uses AIProviderManager and records attributable usage.
- Publishing delegates remote WordPress transport to SiteManager's WordPress client.
- Operations reads other modules for reporting but does not mutate their business state.
- Licensing may request installation entitlements from SubscriptionManager but does not define subscription limits.

## WordPress responsibility

The plugin may cache the latest configuration for resilience, but cached values are never authoritative. It must not choose topics, prompts, providers, schedules, limits, feature availability, or publishing policy. Those decisions are emitted by Laravel as a versioned site configuration.
