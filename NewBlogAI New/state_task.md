# NewsBlogify Feature Implementation Checklist (Staging -> Production)

The following core modules are implemented in the backend, but require integration and connection on the user-end (frontend) to replace simulated blocks or TODO placeholders.

- [x] **Task 1: Advanced Analytics Dashboard Integration**
  - Connect the `node-analytics` workspace UI to fetch and render real-time statistics from `GET /api/v1/analytics/ai` and `GET /api/v1/analytics/content`.
  - Replace placeholders with actual numbers for Total Customers, Articles Generated, AI Requests, and Active Sites.
  - Render actual model usage/provider distributions and top-performing topics.

- [x] **Task 2: Subscription Management Controls**
  - Add action buttons on the `node-billing` workspace for active subscription rows.
  - Implement fully functioning operations on the frontend for: Upgrading/Downgrading plans, Pausing subscriptions, Resuming subscriptions, and Cancelling subscriptions.
  - Hook these up to `/api/v1/customers/{id}/subscription/upgrade`, `/api/v1/customers/{id}/subscription/pause`, etc.

- [x] **Task 3: Category Coverage Dashboard**
  - Fetch and render category coverage metrics from `AnalyticsService::getCategoryCoverageStats()` on the frontend.
  - Add a Category Coverage section or widget in the Topics or Analytics workspace showing empty, stale, fresh, and trending categories.

- [x] **Task 4: Publishing Scheduler & Content Calendar Integration**
  - Update the `node-scheduler` workspace to fetch and list real scheduled items from `/api/v1/schedules` instead of queue jobs.
  - Bind calendar day events to actual scheduled dates.
  - Replace the simulated "Force Sync Release" button with real scheduler dispatching behavior.
