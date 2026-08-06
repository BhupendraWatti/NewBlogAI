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



Rule 19

If any of the skills is mentions and in use check the these path C:\Users\bhupe\.gemini\antigravity-cli\skills and D:\Company Work\Company projects\NewBlogAI\.ai for the specific skill and use it. If you need an more information about the task before beginning ask the user for the correct context instead of the burtforcing. 

Rule 20

After each changes make sure to updates gemini.md and all the  Project Entry Point .md files and tell the user that current changes and current rules (as well as future rules) are all implemented and fixed. 

Rule 21

Background research and real-time news search is strictly restricted to grounded search providers (like Gemini). Non-grounded search query fallback (prompt-based search) is disabled to prevent hallucinations. If grounding is unavailable and no manual newsroom candidate has been selected, the generation run must fail.

Rule 22

During automated/scheduled runs, the pipeline performs duplicate news checks on the top collected source to verify it does not overlap with recently published content, aborting the run if a duplicate is found.

Rule 23

Generated articles are subjected to a Fact Audit. If the calculated fact score is below the mandatory threshold of 70%, the article status is set to 'pending_review' instead of being auto-published/generated, requiring human verification. 

Rule 24

Newsroom candidate discovery target count is 9. To ensure stability and avoid failure on niche regional topics or duplicate-heavy categories, the discovery run is allowed to complete successfully if at least 4 unique candidates are generated. If fewer than 4 unique candidates are found after retries, a shortfall exception is thrown and the run is failed. 

Rule 25

All generated news articles must contain a clear AI disclosure statement at the end, avoid direct verbatim copying to prevent plagiarism, and write with complete neutrality. Visual media comment placeholders must be stripped of double-wrapped or escaped paragraph tags during HTML post-processing. 

Rule 26

Billing ledgers are populated dynamically with tenant-isolated invoices and transactions. Invoices and transactions are created and finalized upon new subscription signups, plan upgrades, and recurring cycles. All public entities (invoices, transactions, coupons) use secure UUIDv7 or ULID string primary keys.

Rule 27

Active expired subscriptions are processed daily by the `subscription-lifecycle-automation` scheduler command. The scheduler attempts to auto-charge the customer's payment method via the active gateway adapter and extends the subscription duration by the billing period (month/year) on successful payment, generating a paid invoice and transaction. If the charge fails, it falls back to marking the subscription as expired.

Rule 28

Every generated content draft, pipeline execution run, and AI request log must track the `user_id` of the actor (employee or system user) who initiated the workflow, ensuring complete user/employee cost attribution across the tenant workspace. 

Rule 29

System authorization roles are restricted to exactly three tiers: Super Admin (1), Customer (2), and Employee (3). All user creation, updates, and access controls validate against this simplified hierarchy.

Rule 30

The system operates strictly under Indian Rupees (INR) for all financial ledgers, invoicing calculations, transactions, and settings. Front-end pricing details and dashboard invoices display the Rupee symbol (₹).

Rule 31

The AI Provider Manager operates a Capability-Aware Router. Stage 1 Live Newsroom Discovery requires real-time Google Search Grounding and routes exclusively through grounded search models (Gemini Free → Gemini Paid), bypassing non-grounded models (Groq) during candidate discovery to prevent fake/stale news filter rejections. Stage 2 Refinement and Stage 3 Article Drafting utilize Groq Free (Llama 3) as a $0 cost high-speed failover when research context is supplied in the prompt.