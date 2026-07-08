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