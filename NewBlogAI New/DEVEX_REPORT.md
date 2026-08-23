# Developer experience review

Date: 2026-08-23  
Evidence: live browser tests, repository inspection, CLI execution, fresh build, migrations, and automated tests

The requested Hall of Fame calibration file was not present in the installed gstack package, so scores use the DevEx skill's published 0–10 rubric and observed project evidence.

## Scorecard

| Dimension | Before | After | Evidence and remaining gap |
|---|---:|---:|---|
| Getting started | 2 | 8 | Stock Laravel README replaced with prerequisites, copy-paste setup, local URL, checks, and architecture map. A containerized one-command environment would reach 10. |
| API and CLI ergonomics | 6 | 6 | Artisan help and invalid-command errors are clear, but there is no NewsBlogify-specific CLI namespace or API reference. |
| Error messages | 4 | 8 | Invalid login is actionable; the dead-end 404 now explains recovery and links to login/dashboard. Domain-specific API errors still vary by module. |
| Documentation | 1 | 8 | Product README and production runbook now cover setup, deployment, workers, scheduler, security, verification, rollback, and monitoring. There is no searchable hosted documentation. |
| Upgrade path | 4 | 5 | Versioned migrations and rollback guidance exist. A user-facing CHANGELOG and upgrade notes are still missing. |
| Developer environment | 5 | 8 | Deterministic npm lockfile, production build, 193-test backend suite, local SQLite path, and CI gate are present. Repository-wide legacy Pint debt remains outside the changed files. |
| Community and ecosystem | 2 | 2 | No contribution guide, issue templates, or community channel was found. This is lower priority for an internal/private product. |
| DX measurement | 1 | 1 | No documentation analytics, developer feedback loop, or onboarding telemetry was found. |

Overall score: **3.1/10 → 5.8/10**. Product-internal onboarding and release operations are now strong; the overall number remains constrained by absent public API docs, upgrade communication, community infrastructure, and DX measurement.

## Time to hello world

Expected fresh setup: **5–8 minutes** when PHP, Composer, Node, and database extensions are already installed. The longest steps are dependency installation and migrations. Missing platform prerequisites can extend this significantly; a containerized development image would reduce variance.

## Highest-value next improvements

1. Publish an OpenAPI reference and document authentication, roles, pagination, and error envelopes.
2. Add `CHANGELOG.md` plus explicit schema/application compatibility notes for each release.
3. Add a Docker-based development profile or a verified Windows bootstrap script.
4. Pay down repository-wide Pint failures and make formatting a required CI check.
5. Add non-production integration fixtures for AI-provider and WordPress end-to-end smoke tests.
