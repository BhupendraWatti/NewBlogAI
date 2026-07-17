# BUSINESS RULES

Rule 1

One website belongs to one customer.

Rule 2

One customer can own multiple websites depending on subscription.

Rule 3

Topics belong to subscriptions.

Rule 4

Prompt Templates belong to Topics.

Rule 5

Publishing Schedule belongs to Website.

Rule 6

Plugin never generates prompts.

Prompt generation always happens in Laravel.

Rule 7

Plugin only receives ready-to-publish content.

Rule 8

API Keys are encrypted.

Rule 9

Deleting a website must also revoke plugin authentication.

Rule 10

Every database transaction must preserve data consistency.

No business rule should exist in multiple places.

Rule 11

Original database test data must be preserved. Do not clear database tables or run migrate:fresh unless explicitly requested.

Rule 12

Current features and code structure are working. Do not modify or refactor existing functional code unless implementing new specifications or fixing bugs.

Rule 13

Analytics dashboards and per-site analytics endpoints require plan-level analytics entitlement access (`analytics_access`), except for SuperAdmin/Support roles.

Rule 14

Workspace and employee team structures are isolated by Tenant (Customer). Adding team members requires workspace update authority and the user must belong to the same customer unless the acting user is a SuperAdmin/Admin. The last Owner of a workspace cannot be removed.

Rule 15

Multiple runs for the same pipeline must not execute concurrently. Cache-driven atomic locks must prevent duplicate processing by queue workers.

Rule 16

To prevent mis-framing past occurrences as breaking news, the system parses chronological context from the source snippets and candidates to determine if a story is a follow-up (event lag >= 48 hours), and enriches prompt generation with temporal framing directives.

Rule 17

When an AI request fails, the pipeline must attempt automatic failover to alternative enabled providers (prioritizing groq, gemini, openai, claude, openrouter, ollama) with exponential backoff on retryable errors. If a provider's credentials fail authentication, it must immediately fail over without wasteful retries, and flag the key. Keys are masked in serializations to protect credentials.

Rule 18

AI Providers track dynamic credits and freshness. System settings option `enable_image_generation` can be toggled to disable image generation requests. Manual credit refresh must be supported to fetch accurate limits upon key updates.